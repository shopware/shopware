---
title: Symfony Dependency Management
date: 2023-05-16
area: core
tags: [php, symfony, dependency]
---

## Context

The process of configuring dependencies has been upgraded with various new features in recent versions of Symfony.

We would like to utilise new features such as:

* [Autowiring](https://symfony.com/doc/current/service_container.html)
* [PHP configuration](https://symfony.com/doc/current/service_container/import.html)
* [Attributes for autowiring](https://symfony.com/blog/new-in-symfony-6-1-service-autowiring-attributes)

## Decision

1. Autowiring will be enabled
2. Support will be added to load service configuration from PHP files (as well as XML for backwards compatibility)
3. Where services need a particular non default service, for example a different implementation, or scalar values, we can use attributes.

Note: Attributes should only be used in framework glue code, for example, in Controllers and commands. We do not want to couple our domain code too close to Symfony.

With autowiring enabled, we can greatly reduce the amount of configuration in the XML files since most of the configuration is unnecessary. Most dependency graphs can be automatically resolved by Symfony using type hints.
There are no runtime performance implications because the container with its definitions is compiled.

Advantages:

* Less code to maintain.
* Better autocompletion with PHP.
* More modern approach

## Backwards Compatibility / Migration Strategy

To migrate our current XML dependency configurations, we can follow the below steps:

Step 1: Add support for loading service definitions from PHP files as well as XML files.

Step 2: Enable autowiring. Symfony should prefer the registered configuration before trying to autowire. In other words, Symfony will only autowire classes without configured definitions.

Step 3: Delete definitions which are not required because they can be autoloaded.

Step 4: Migrate any existing definitions to the PHP configurations or Attributes.
