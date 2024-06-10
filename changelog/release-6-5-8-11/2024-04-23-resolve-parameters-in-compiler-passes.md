---
title: Resolve extension parameters in Shopware compiler passes
issue: NEXT-36143
flag:
author: Philip Standt
author_email: philip@maphi.net
author_github: @Ocarthon
---
# Core
* Changed `\Shopware\Core\Framework\DependencyInjection\CompilerPass\CompilerPassConfigTrait` to resolve extension parameters before processing the values.

