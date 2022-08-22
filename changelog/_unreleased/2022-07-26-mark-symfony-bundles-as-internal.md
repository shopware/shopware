---
title: Mark symfony bundles as internal
issue: NEXT-21194
---
# Core
* Changed all sub-classes of `\Shopware\Core\Framework\Bundle` to be internal, as those classes are used by symfony, but should not be used by 3rd party developers.
