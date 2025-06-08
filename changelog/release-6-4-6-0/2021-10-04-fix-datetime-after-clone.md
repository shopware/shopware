---
title: Fix datetime after clone
issue: NEXT-17728
author: Vitalij Mik
author_email: cccpmik@gmail.com 
author_github: BlackScorp
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\VersionManager::cloneEntity()` to reset the createdAt and modifiedAt timestamps if exists 
