---
title: Add twig filter improvements
issue: NEXT-24667
---

# Core

* Added a `SecurityExtension` to allow only a whitelist of functions inside filters `map`, `filter`, `reduce` and `sort`.

___

# Upgrade Information

## Twig filter whitelist for `map`, `filter`, `reduce` and `sort`

The whitelist can be extended using a yaml configuration:

```yaml
shopware:
    twig:
        allowed_php_functions: [ "is_bool" ]
```
