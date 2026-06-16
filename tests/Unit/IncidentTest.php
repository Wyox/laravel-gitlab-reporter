<?php

namespace Wyox\GitlabReport\Tests\Unit;

use Exception;
use Illuminate\Http\Request;
use Wyox\GitlabReport\Incidents\CommandIncident;
use Wyox\GitlabReport\Incidents\RequestIncident;
use Wyox\GitlabReport\Tests\TestCase;

class IncidentTest extends TestCase
{
    public function test_command_incident_generates_consistent_hash(): void
    {
        $exception = new Exception('Test error', 500);
        $argv = ['artisan', 'test:command'];

        $incident1 = new CommandIncident($exception, $argv);
        $incident2 = new CommandIncident($exception, $argv);

        $this->assertEquals($incident1->hash(), $incident2->hash());
    }

    public function test_command_incident_title_contains_message(): void
    {
        $exception = new Exception('Something went wrong');
        $incident = new CommandIncident($exception, ['artisan', 'test']);

        $this->assertStringContainsString('Something went wrong', $incident->title());
        $this->assertStringStartsWith('BUG:', $incident->title());
    }

    public function test_command_incident_title_truncated_at_254_chars(): void
    {
        $longMessage = str_repeat('x', 300);
        $exception = new Exception($longMessage);
        $incident = new CommandIncident($exception, []);

        $this->assertLessThanOrEqual(254, strlen($incident->title()));
    }

    public function test_command_incident_generates_markdown(): void
    {
        $exception = new Exception('Test error');
        $incident = new CommandIncident($exception, ['artisan', 'migrate']);

        $markdown = $incident->markdown();

        $this->assertIsString($markdown);
        $this->assertNotEmpty($markdown);
    }

    public function test_request_incident_generates_consistent_hash(): void
    {
        $exception = new Exception('Request error');
        $request = Request::create('/api/test', 'POST', ['name' => 'test']);

        $incident1 = new RequestIncident($exception, $request);
        $incident2 = new RequestIncident($exception, $request);

        $this->assertEquals($incident1->hash(), $incident2->hash());
    }

    public function test_request_incident_signature_contains_path(): void
    {
        $exception = new Exception('Test error');
        $request = Request::create('/users/123', 'GET');

        $incident = new RequestIncident($exception, $request);

        $this->assertStringContainsString('users/123', $incident->signature());
    }

    public function test_different_exceptions_produce_different_hashes(): void
    {
        $exception1 = new Exception('Error 1');
        $exception2 = new Exception('Error 2');

        $incident1 = new CommandIncident($exception1, ['artisan', 'test']);
        $incident2 = new CommandIncident($exception2, ['artisan', 'test']);

        $this->assertNotEquals($incident1->hash(), $incident2->hash());
    }

    public function test_incident_uses_class_name_when_no_message(): void
    {
        $exception = new Exception('');
        $incident = new CommandIncident($exception, []);

        $this->assertStringContainsString('Exception', $incident->title());
    }

    public function test_dynamic_ids_in_message_collapse_to_same_hash(): void
    {
        // Both exceptions are created on the same line so file:line is identical;
        // only the dynamic id in the message differs.
        $make = fn (string $message) => new Exception($message);

        $first = new CommandIncident($make('No query results for model [User] 12345'), ['artisan', 'test']);
        $second = new CommandIncident($make('No query results for model [User] 67890'), ['artisan', 'test']);

        $this->assertEquals($first->hash(), $second->hash());
    }

    public function test_uuid_in_message_collapse_to_same_hash(): void
    {
        $make = fn (string $message) => new Exception($message);

        $first = new CommandIncident($make('Job 7b3f1c2a-8d4e-4f1a-9b2c-1a2b3c4d5e6f failed'), ['artisan', 'test']);
        $second = new CommandIncident($make('Job 1c2d3e4f-5a6b-7c8d-9e0f-a1b2c3d4e5f6 failed'), ['artisan', 'test']);

        $this->assertEquals($first->hash(), $second->hash());
    }

    public function test_genuinely_different_messages_keep_different_hashes(): void
    {
        $make = fn (string $message) => new Exception($message);

        $first = new CommandIncident($make('Database connection refused'), ['artisan', 'test']);
        $second = new CommandIncident($make('Permission denied for storage path'), ['artisan', 'test']);

        $this->assertNotEquals($first->hash(), $second->hash());
    }
}
