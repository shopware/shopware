---
title: Fix CMS versionable migration
issue: NEXT-15186
flag:
author: Jan Pietrzyk
author_email:
author_github:
---
# Core
* Changed `src/Core/Framework/Migration/MakeVersionableMigrationHelper.php` so that `strtolower` is used to prevent errors when creating foreign keys 
