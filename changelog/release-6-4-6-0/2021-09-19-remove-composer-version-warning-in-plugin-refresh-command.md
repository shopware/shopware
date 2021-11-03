---
title: 2021-09-19-Remove-Composer-Version-Warning-In-Plugin-Refresh-Command
issue: NEXT-17417
author: Edip Aydin
author_email: ea@networker.de 
author_github: Edip Aydin
---
# Core
* Removed version check when reading plugin composer.json to avoid warnings for all plugins in `Shopware\Core\Framework\Plugin\Composer\PackageProvider::getPluginComposerPackage()`
