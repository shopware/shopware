---
title: Fixes nullable return type for OAuth/ScopeRepository
issue: NEXT-16526
author: Rico Neitzel
author_email: rico@run-as-root.sh
author_github: @riconeitzel
---
# Core
* Changed `Shopware\Core\Framework\Api\OAuth\ScopeRepository::getScopeEntityByIdentifier()` to support a null return value.
