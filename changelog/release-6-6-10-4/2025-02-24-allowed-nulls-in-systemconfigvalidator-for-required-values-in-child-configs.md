---
title: Allowed nulls in SystemConfigValidator for required values in child-configs
issue: 5615
---
# Core
* Changed `\Shopware\Core\System\SystemConfig\Validation\SystemConfigValidator` to allow null values for required fields in child configs. Null values are used to remove inheritance in `SystemConfigService`. 
