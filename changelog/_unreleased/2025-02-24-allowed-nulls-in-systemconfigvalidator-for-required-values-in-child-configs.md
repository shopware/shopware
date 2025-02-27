---
title: Allowed nulls in SystemConfigValidator for required values in child-configs
issue: NEXT-29637
---
# Core
* Changed `\Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator` to allow null values for required fields in child configs. Null values are used to remove inheritance in `SystemConfigService`. 
