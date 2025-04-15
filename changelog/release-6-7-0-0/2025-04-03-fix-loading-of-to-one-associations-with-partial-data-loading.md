---
title: Fix loading of to one associations with partial data loading
author: Pascal Paul
author_email: pascal.paul@pickware.de
author_github: @pascalniklaspaul
---
# Core
* Changed `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityReader` to always load the reference field of to-one associations when using partial data loading.
