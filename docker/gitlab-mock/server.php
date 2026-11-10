<?php

// Minimal stand-in for the GitLab issues API the reporter talks to.
// Issues are stored as issues/<iid>.json + issues/<iid>.md (open the .md in your editor to review the rendering).
// Payloads GitLab would reject are rejected here too, and the reason is logged by `docker compose logs gitlab`.

$dir = __DIR__ . '/issues';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

function respond(int $status, mixed $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
}

if (empty($_SERVER['HTTP_PRIVATE_TOKEN'])) {
    respond(401, ['message' => '401 Unauthorized']);
    return;
}

if (!preg_match('#^/api/v4/projects/([^/]+)/issues$#', $path, $match)) {
    respond(404, ['message' => '404 Not Found']);
    return;
}

$issues = array_map(fn ($file) => json_decode(file_get_contents($file), true), glob("$dir/*.json") ?: []);

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search = $_GET['search'] ?? '';
    $state = $_GET['state'] ?? null;
    respond(200, array_values(array_filter(
        $issues,
        fn ($issue) => (!$state || $issue['state'] === $state)
            && ($search === '' || str_contains($issue['title'] . $issue['description'], $search))
    )));
    return;
}

$raw = file_get_contents('php://input');
if (str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'json')) {
    $input = json_decode($raw, true) ?? [];
} else {
    parse_str($raw, $input);
}

$title = $input['title'] ?? '';
$description = $input['description'] ?? '';
$errors = [];

if ($title === '') {
    $errors[] = 'title is missing';
}
foreach (['title' => $title, 'description' => $description] as $field => $value) {
    if (!mb_check_encoding($value, 'UTF-8')) {
        $errors[] = "$field is not valid UTF-8";
    }
}
if (mb_strlen($title) > 255) {
    $errors[] = 'title is too long (maximum is 255 characters)';
}
if (mb_strlen($description) > 1048576) {
    $errors[] = 'description is too long (maximum is 1048576 characters)';
}

if ($errors) {
    error_log('Rejected issue: ' . implode('; ', $errors));
    respond(400, ['message' => $errors]);
    return;
}

$iid = count($issues) + 1;
$issue = [
    'id' => $iid,
    'iid' => $iid,
    'project_id' => $match[1],
    'title' => $title,
    'description' => $description,
    'state' => 'opened',
    'labels' => array_values(array_filter(explode(',', $input['labels'] ?? ''))),
    'issue_type' => $input['issue_type'] ?? 'issue',
];
file_put_contents("$dir/$iid.json", json_encode($issue, JSON_PRETTY_PRINT));
file_put_contents("$dir/$iid.md", "# $title\n\n$description");
error_log("Created issue #$iid: $title");

respond(201, $issue);
