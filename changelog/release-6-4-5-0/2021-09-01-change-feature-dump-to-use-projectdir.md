---
title: Changed command "feature:dump" to use Kernel::getProjectDir()
issue: NEXT-17010
author: mynameisbogdan
author_email: mynameisbogdan@protonmail.com
author_github: mynameisbogdan
---
# Core
* Changed `Shopware\Core\Framework\Feature\Command\FeatureDumpCommand` to use Kernel::getProjectDir() instead of using a relative path to Kernel::getCacheDir()
