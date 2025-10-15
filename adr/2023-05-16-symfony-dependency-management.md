---
title: Symfony Dependency Management
date: 2023-05-16
area: core
tags: [php, symfony, dependency]
---

## Context

Recent versions of Symfony have introduced various new features for dependency management that simplify service configuration and improve the developer experience.

These features are now available in Shopware and include:

* [Autowiring](https://symfony.com/doc/current/service_container.html)
* [PHP configuration](https://symfony.com/doc/current/service_container/import.html)
* [Attributes for autowiring](https://symfony.com/blog/new-in-symfony-6-1-service-autowiring-attributes)

## Decision

Shopware now supports the following modern Symfony dependency management features:

1. **Autowiring**: Services can be automatically resolved by Symfony using type hints, reducing the need for explicit service definitions.
2. **PHP-based service configuration**: Service configuration can be loaded from PHP files in addition to XML and YAML.
3. **Autowire attribute**: Services requiring non-default implementations or scalar values can use the `Autowire` attribute for configuration.

**Note**: Attributes should only be used in framework glue code, such as Controllers and Commands.
Domain code should remain decoupled from Symfony-specific implementations.

With autowiring enabled, dependency graphs can be automatically resolved by Symfony using type hints, significantly reducing configuration overhead.

## Benefits for plugin development

Plugin developers can now leverage these features to:

* Reduce boilerplate configuration code
* Benefit from better IDE autocompletion with PHP-based configuration
* Use a more modern, streamlined approach
