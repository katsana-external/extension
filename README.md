Extension Component for Orchestra Platform
==============

Extension Component allows components or packages to be added dynamically to Orchestra Platform without the hassle of modifying the configuration.

[![Build Status](https://travis-ci.org/orchestral/extension.svg?branch=3.7)](https://travis-ci.org/orchestral/extension)
[![Latest Stable Version](https://poser.pugx.org/orchestra/extension/version)](https://packagist.org/packages/orchestra/extension)
[![Total Downloads](https://poser.pugx.org/orchestra/extension/downloads)](https://packagist.org/packages/orchestra/extension)
[![Latest Unstable Version](https://poser.pugx.org/orchestra/extension/v/unstable)](//packagist.org/packages/orchestra/extension)
[![License](https://poser.pugx.org/orchestra/extension/license)](https://packagist.org/packages/orchestra/extension)
[![Coverage Status](https://coveralls.io/repos/github/orchestral/extension/badge.svg?branch=3.7)](https://coveralls.io/github/orchestral/extension?branch=3.7)

## Table of Content

* [Version Compatibility](#version-compatibility)
* [Installation](#installation)
* [Configuration](#configuration)
* [Resources](#resources)
* [Changelog](https://github.com/orchestral/extension/releases)

## Version Compatibility

Laravel    | Extension
:----------|:----------
 4.x.x     | 2.x.x
 5.0.x     | 3.0.x
 5.1.x     | 3.1.x
 5.2.x     | 3.2.x
 5.3.x     | 3.3.x
 5.4.x     | 3.4.x
 5.5.x     | 3.5.x
 5.6.x     | 3.6.x
 5.7.x     | 3.7.x

## Installation

To install through composer, simply put the following in your `composer.json` file:

```json
{
    "require": {
        "orchestra/extension": "^3.0"
    }
}
```

And then run `composer install` from the terminal.

### Quick Installation

Above installation can also be simplify by using the following command:

    composer require "orchestra/extension=^3.0"

## Configuration

Next add the following service provider in `config/app.php`.

```php
'providers' => [

    // ...

    Orchestra\Extension\ExtensionServiceProvider::class,
    Orchestra\Memory\MemoryServiceProvider::class,
    Orchestra\Publisher\PublisherServiceProvider::class,

    Orchestra\Extension\CommandServiceProvider::class,
],
```

### Migrations

Before we can start using Extension Component, please run the following:

    php artisan extension:migrate

> The command utility is enabled via `Orchestra\Extension\CommandServiceProvider`.

