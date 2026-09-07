# Shopware Test Suite

A standalone Composer library for PHPUnit testing utilities, intended to be installed as a development dependency in Shopware projects.

This package is currently a scaffold for extracting Shopware’s testing framework, including `TestBootstrapper`, all shared base behaviours and helpers, kernel lifecycle management, and PHPUnit integration. These APIs are planned but not implemented yet. See the [source inventory and extraction scope](docs/extraction-scope.md).

## Versioning and dependencies

The package has its own release cycle and semantic versions, independent of Shopware core releases. The empty scaffold currently has no dependency on `shopware/core` or `shopware/platform`. The extracted integration framework will depend on a tested range of core versions, independently of this package’s own version, without `self.version` constraints. Independent releases do not mean that kernel and DAL testing can run without Shopware. No supported core range has been established yet.

PHPUnit starts at `^11.5.19`, matching the current development baseline. Support for additional PHPUnit majors should be added when validated.

This is a Composer library (`type: library`), so it needs no Shopware plugin class or activation.

## Structure

- `src/`: reusable utilities under `Shopware\TestSuite\`.
- `tests/`: the package's own tests under `Shopware\TestSuite\Tests\`.
- `phpunit.xml.dist`: standalone test configuration using the package's Composer autoloader.

## Local development

From this directory:

```sh
composer install
composer test
```

The test command intentionally fails while the suite is empty. Add meaningful tests alongside the first implementation.

The Shopware checkout already discovers `custom/static-plugins/*` through a Composer path repository. To use this unpublished package in that checkout, explicitly add it as a development dependency:

```sh
composer require --dev 'shopware/test-suite:@dev'
```

Run that command from the checkout root when ready to integrate the package. For another project, first configure a Composer path repository pointing to this directory. Once published, consumers can require a tagged version independently of their Shopware core version.
