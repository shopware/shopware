---
title: Use parameters to display cache information in admin.
issue: https://github.com/shopware/platform/issues/2369
author: Andreas Allacher
author_email: andreas.allacher@massiveart.com
author_github: @AndreasA
---
# Core
* Changed `Shopware\Core\Framework\Api\Controller\CacheController` to retrieve cache information from parameters.
* Changed `Shopware\Core\Framework\Api\Controller\CacheController` service definition to use `'container.service_subscriber` for the container, so `\Symfony\Bundle\FrameworkBundle\Controller\AbstractController::getParameter` works.
