---
title: Removed hard coded dependency on redis in shopware config
issue: NEXT-34648
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Removed the parameter `redis_url` from the `config/packages/shopware.yaml` file in the `cart` and `number_range` sections.
* Added the `storage` parameter to the `cart` section in the `config/packages/shopware.yaml` file. The `storage.type` parameter can be set to `mysql` or `redis`. 
* Changed the `increment_storage` parameter in the `number_range` section in the `config/packages/shopware.yaml` file to accept values `mysql` or `redis`.
* Added the `config` parameter to the `cart` and `number_range` sections in the `config/packages/shopware.yaml` file. The `config` parameter can be used to set the `dsn` parameter for the `mysql` or `redis` storage.
___
# Upgrade Information
## Configure Redis for cart storage
When you are using Redis for cart storage, you should add the following config inside `shopware.yaml`:
```yaml
    cart:
        compress: false
        expire_days: 120
        storage:
            type: "redis"
            config:
                dsn: 'redis://localhost'
```
## Configure Redis for number range storage
When you are using Redis for number range storage, you should add the following config inside `shopware.yaml`:
```yaml
    number_range:
        increment_storage: "redis"
        config:
            dsn: 'redis://localhost'
```
___
# Next Major Version Changes
## Shopware config changes:
### cart
Replace the `redis_url` parameter in `config/packages/shopware.yaml` file:
```yaml
    cart:
        compress: false
        expire_days: 120
        redis_url: false # or 'redis://localhost'
```
to
```yaml
    cart:
        compress: false
        expire_days: 120
        storage:
            type: "mysql" # or "redis"
            # config:
                # dsn: 'redis://localhost'
```
### number_range
Replace the `redis_url` parameter in `config/packages/shopware.yaml` file:
```yaml
    number_range:
        increment_storage: "SQL"
        redis_url: false # or 'redis://localhost'
```
to
```yaml
    number_range:
        increment_storage: "mysql" # or "redis"
        # config:
            # dsn: 'redis://localhost'
```
