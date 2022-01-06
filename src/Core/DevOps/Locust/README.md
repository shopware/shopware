# Quick start

## Installation
Install locust on the machine which executes the benchmark. https://docs.locust.io/en/stable/installation.html

## Setup
To keep the test simple and flexible, the script needs some data from the database.
The corresponding data can also be determined automatically from the `setup.php` file and written into the corresponding *.csv files.
If the benchmark should not take place locally or directly within the Shopware installation, the data must be transferred accordingly into the *.csv files.
```shell
php dev-ops/locust/setup.php
```

## Enabled cache
Since locust is a benchmark script, the caches should be enabled.
Simply add the following section to one of your local configuration files in {root}/config/packages/*.yaml. 
(Choose redis, if you have a multi app server setup)

```yaml
framework:
    cache:
        app: cache.adapter.filesystem

#framework:
#    cache:
#        app: cache.adapter.redis
```

## Disabled csrf protection 
To allow registrations and tracing the order process, the csrf protection has to be disabled. 
Simply add the following section to one of your local configuration files in {root}/config/packages/*.yaml

```yaml
storefront:
    csrf:
        enabled: false
```
