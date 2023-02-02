[titleEn]: <>(How to log)
[metaDescriptionEn]: <>(This article shows you how to configure default logger settings and create logger instances)
[hash]: <>(article:how_to_log)

## Overview

Shopware 6 uses Monolog loggers and provide a central factory for the most common logging cases.

## Setup a logger

You can use the LoggerFactory class to generate a logger service within your service definition:

<div class="tabs">
    <nav>
        <a href="#">XML</a>
        <a href="#">YML</a>
    </nav>
    <div class="tabs-container">
        <section>
            <pre><code><service id="acme_powerful_plugin.foobar.logger" class="Psr\Log\LoggerInterface">
    <factory service="Shopware\Core\Framework\Log\LoggerFactory" method="createRotating"/>
    <argument type="string">acme_powerful_plugin_foobar</argument>
    <argument>90</argument>
</service>
</code></pre>
        </section>
        <section>
            <pre><code>acme_powerful_plugin.foobar.logger:
    class: Psr\Log\LoggerInterface
    factory:
        - '@Shopware\Core\Framework\Log\LoggerFactory'
        - createRotating
    arguments:
        - acme_powerful_plugin_foobar
        - 90
</code></pre>
        </section>
    </div>
</div>

This provides a logger that has a file rotation of 90 days.
So every file older than 90 days will be deleted.
When you omit the limit the configuration key `shopware.logger.file_rotation_count` is read for the default file rotation.
Using the value `0` will stop the rotation at all.

## Configuration

The configuration for Shopware 6 resides in the general bundle configuration:

```
<development root>
└── config
   └── packages
      └── shopware.yml
```

To change default logging behaviour for your shop you need to add the `logger:` map to 
the `shopware.yml`:

```yaml
shopware:
    logger:
        file_rotation_count: 90 # defaults to 14
```
