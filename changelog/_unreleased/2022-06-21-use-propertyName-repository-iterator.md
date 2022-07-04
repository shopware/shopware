---
title: Fixed src/Core/Framework/DataAbstractionLayer/Dbal/Common/RepositoryIterator.php::fetchIds() with propertyName instead of storageName
issue: NEXT-22085
author: Léo Cunéaz
author_github: @inem0o
---
# Core
* Changed `src/Core/Framework/DataAbstractionLayer/Dbal/Common/RepositoryIterator.php::fetchIds()` to use automatically the autoincrement field propertyName instead of the storageName
