---
title: Remove definition from WriteCommand
issue: NEXT-32255
author: Jozsef Damokos
author_email: j.damokos@shopware.com
author_github: jozsefdamokos
---
# Core
* Removed `$definition` parameter from `WriteCommand`. This is replaced by `$entityName` parameter.
* Deprecated `getDefinition()` method from `WriteCommand` and its extension classes.
