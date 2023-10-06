---
title: Changed SwInclude Node
issue: NEXT-16783
author: Stefan Sluiter
author_github: ssltg
---
# Core
* Changed `Shopware\Core\Framework\Adapter\Twig\Node\SwInclude` to not resolve namespace of include template because it will be resolved later in the finder.
