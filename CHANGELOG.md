# Changelog

## 1.1.3

- Update packages dependencies
- Remove deprecated usage of `Client::create()` and use `new Gitlab\Client()` instead.

## 1.1.2

- Update packages dependencies

## 1.1.1

- Fix labels not being applied from configuration
- Add support for Laravel 8
- Ability to add labels when reporting (thanks menkaff!)

## 1.1.0

- Add proper support for Laravel 7 by replacing Exception with Throwable

## 1.0.1

- Add support for Laravel 7

## 1.0.0

- Update requirements to PHP 7.1
- Update readme with a better check if gitlab.report is bound
- Add details tag to markdown messages to hide GET/POST/SESSION by default in an issue
- Add user details if available to the ticket
- Refactor code
- Update to latest guzzlehttp and php-gitlab-api

## 0.0.7

Updated readme to reflect a production environment where the config is cached and env() calls are no longer working.

- Update readme to change env('APP_ENV') entry to config('app.env') for production environments

## 0.0.6
- Added support for Laravel 5.8

## 0.0.5
- Ability to add labels to a ticket


## 0.0.1
- Initial release

