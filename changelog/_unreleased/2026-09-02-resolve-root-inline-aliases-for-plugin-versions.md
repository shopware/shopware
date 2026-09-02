---
title: Resolve root inline aliases for plugin versions
issue: NEXT-00000
---
# Core
* Changed `Shopware\Core\Framework\Plugin\Util\PluginFinder` to resolve root-level composer inline aliases (e.g. requiring a plugin as `dev-bugfix as 1.2.3`), so the plugin version is reported as the aliased version instead of the raw branch version. Previously the branch version (e.g. `dev-bugfix`) was written to the plugin table, where it compared as older than any release version and caused plugins to re-run all of their update migration steps on the next update.
