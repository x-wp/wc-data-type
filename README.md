<div align="center">

<h1 align="center" style="border-bottom: none; margin-bottom: 0px">WC Data Type</h1>
<h3 align="center" style="margin-top: 0px">Model-driven custom data objects for WooCommerce</h3>

[![Packagist Version](https://img.shields.io/packagist/v/x-wp/wc-data-type?label=Release&style=flat-square)](https://packagist.org/packages/x-wp/wc-data-type)
![Packagist PHP Version](https://img.shields.io/packagist/dependency-v/x-wp/wc-data-type/php?label=PHP&logo=php&logoColor=white&logoSize=auto&style=flat-square)
![Static Badge](https://img.shields.io/badge/WP-%3E%3D6.9.4-3858e9?style=flat-square&logo=wordpress&logoSize=auto)
![Static Badge](https://img.shields.io/badge/WC-%3E%3D10.6.1-7f54b3?style=flat-square&logo=woocommerce&logoSize=auto)
[![GitHub Actions Workflow Status](https://img.shields.io/github/actions/workflow/status/x-wp/wc-data-type/tests.yml?label=Tests&event=push&style=flat-square&logo=githubactions&logoColor=white&logoSize=auto)](https://github.com/x-wp/wc-data-type/actions/workflows/tests.yml)

</div>

This library provides a standardized way to define and work with custom WooCommerce-style data objects. Define a model once, then reuse consistent property handling, factories, queries, and data-store behavior around it.

## Key Features

1. Model-driven setup: Define object shape with the `#[Model]` attribute.
2. WooCommerce-style objects: Extend `XWC_Data` and keep a familiar CRUD workflow.
3. Typed properties: Support for core props, meta props, and taxonomy props.
4. Shared object helpers: Load single objects or collections through package utilities.
5. Extensible architecture: Swap in custom data stores, factories, and meta stores when needed.
6. WordPress-native integration: Designed for plugin code already built around WordPress and WooCommerce lifecycles.

## Installation

You can install this package via Composer:

```bash
composer require x-wp/wc-data-type
```

> [!TIP]
> We recommend using `automattic/jetpack-autoloader` with this package to reduce autoloading conflicts in WordPress environments.

## Usage

Below is a simple example that defines a custom data object and loads it through the package helpers.

### Defining a model

```php
<?php

use XWC\Data\Decorators\Model;

#[Model(
    name: 'book',
    table: '{{PREFIX}}books',
    core_props: array(
        'title' => array(
            'default' => '',
            'type'    => 'string',
        ),
        'slug' => array(
            'default' => '',
            'type'    => 'slug',
        ),
        'price' => array(
            'default' => 0,
            'type'    => 'float',
        ),
    ),
)]
final class Book extends XWC_Data {
    protected $object_type = 'book';
}
```

### Loading objects

```php
<?php

$book = xwc_get_object(15, 'book', null);

$books = xwc_get_objects(
    'book',
    array(
        'limit'   => 10,
        'orderby' => 'title',
        'order'   => 'ASC',
    ),
);
```

Generated getters and setters follow the declared props, so classes like `Book` can expose methods such as `get_title()`, `set_title()`, `get_slug()`, and `set_price()`.

## Testing

The package ships with a PHPUnit suite that boots a WordPress test environment and exercises model definitions, object factories, queries, props, and runtime object behavior.

Run the suite with:

```bash
composer test
```

To prepare the local WordPress test environment first:

```bash
composer test:install
composer test
```

To clean the local test environment:

```bash
composer test:clean
```

## Documentation

For package-specific usage, start with the public entrypoints used throughout the library:

- `XWC\Data\Decorators\Model`
- `XWC_Data`
- `xwc_get_object()`
- `xwc_get_objects()`

Additional project information is available in the [repository](https://github.com/x-wp/wc-data-type).

For maintainers preparing prereleases or stable tags, see [docs/release-process.md](docs/release-process.md).
