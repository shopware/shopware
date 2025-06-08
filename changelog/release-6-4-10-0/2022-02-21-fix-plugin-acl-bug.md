---
title: Fix 'Plugin ACL' bug
issue: NEXT-20222
author: Rune Laenen
author_email: rune@laenen.me
author_github: runelaenen
---
# Core
* Added `array_values` in `\Shopware\Core\Framework\Plugin\Subscriber\PluginAclPrivilegesSubscriber::onAclRoleLoaded`.