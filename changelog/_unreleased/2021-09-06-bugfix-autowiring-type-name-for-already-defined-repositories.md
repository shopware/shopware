---
title: Bugfix autowiring type+name for already defined repositories
issue: NEXT-17058
author: mynameisbogdan
author_email: mynameisbogdan@protonmail.com
author_github: mynameisbogdan
---
# Core
* Changed `Shopware\Core\Framework\DependencyInjection\CompilerPass\EntityCompilerPass` to add `registerAliasForArgument` for already defined repositories and move duplicated calls after try-catch.
