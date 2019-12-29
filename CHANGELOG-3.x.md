# Changelog for 3.x

This changelog references the relevant changes (bug and security fixes) done to `orchestra/extension`.

## 3.8.2

Released: 2019-10-09

### Fixes

* Fixes `php artisan extension:detect` command.

## 3.8.1

Released: 2019-08-04

### Changes

* Use `static function` rather than `function` whenever possible, the PHP engine does not need to instantiate and later GC a `$this` variable for said closure.

## 3.8.0

Released: 2019-03-16

### Changes

* Update support for Laravel Framework v5.8.
* Refactor codes to utilize Laravel's Collection. 

## 3.7.1

Released: 2019-02-21

### Changes

* Improve performance by prefixing all global functions calls with `\` to skip the look up and resolve process and go straight to the global function.

## 3.7.0

Released: 2018-09-14

### Changes

* Update support for Laravel Framework v5.7.

### Removed

* Remove deprecated `Orchestra\Extension\Traits\DomainAware`, use `Orchestra\Extension\Concerns\DomainAware` instead.

## 3.6.0

Released: 2018-05-06

### Added

* Added `Orchestra\Extension\Concerns\DomainAware`.

### Changes

* Update support for Laravel Framework v5.6.

### Deprecated

* Deprecate `Orchestra\Extension\Traits\DomainAware`, use `Orchestra\Extension\Concerns\DomainAware` instead.