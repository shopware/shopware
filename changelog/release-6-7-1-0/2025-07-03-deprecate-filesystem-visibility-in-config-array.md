---
title: Deprecate filesystem visibility in config array
---

# Core

* Deprecated that filesystem visibility can be configured in the config array. It should be configured on the same level as `type`.

___
# Upgrade Information

## Deprecated configuration of visibility in config array

The visibility of filesystems should no longer be configured in the config array. Instead, it should be set on the same level as `type`. For example, instead of:

```yaml
filesystems:
  my_filesystem:
    type: local
    config:
      visibility: public
```

You should now use:

```yaml
filesystems:
  my_filesystem:
    type: local
    visibility: public
```
