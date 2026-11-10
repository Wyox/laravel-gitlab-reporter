#!/usr/bin/env bash
# Install one Laravel version and run PHPUnit. Used by docker-compose (test service), bin/matrix and GitLab CI.
#
#   docker/run-tests.sh <laravel: 12|13|14> [stable|lowest] [phpunit args...]
#
# 14 = unreleased Laravel (laravel/framework dev-master).
# With WORK_ROOT set, the checkout is synced to $WORK_ROOT/<combo> first, so composer.json in the
# checkout is never touched and every combination keeps its own vendor/. Without it, runs in place (CI).
set -euo pipefail

laravel=${1:-13}
deps=${2:-stable}
shift $(( $# < 2 ? $# : 2 ))

if [[ -n "${WORK_ROOT:-}" ]]; then
    work="$WORK_ROOT/php$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')-laravel$laravel-$deps"
    mkdir -p "$work"
    rsync -a --delete --exclude /vendor --exclude /composer.lock --exclude /.git --exclude /.matrix "$PWD"/ "$work"/
    cd "$work"
fi

case $laravel in
    12) req=("laravel/framework:^12.0" "orchestra/testbench:^10.0") ;;
    13) req=("laravel/framework:^13.0" "orchestra/testbench:^11.0") ;;
    14) composer config minimum-stability dev
        composer config prefer-stable true
        # Alias so dev-master satisfies our illuminate/support ^13 constraint.
        req=("laravel/framework:dev-master as 13.99.0" "orchestra/testbench:12.x-dev") ;;
    *) echo "Unknown Laravel version '$laravel' (expected 12, 13 or 14)" >&2; exit 2 ;;
esac

composer require --dev --no-update --no-interaction "${req[@]}"

flags=(--no-interaction --no-progress)
[[ $deps == lowest ]] && flags+=(--prefer-lowest --prefer-stable)
composer update "${flags[@]}"

vendor/bin/phpunit "$@"
