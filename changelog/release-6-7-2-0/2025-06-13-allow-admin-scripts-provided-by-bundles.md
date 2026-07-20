---
title: Allow admininistration scripts being provided by bundles
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed focus in administration asset path detection from `\Shopware\Core\Framework\Plugin` to `\Shopware\Core\Framework\Bundle` to be able to load meteor apps from bundles  
* Changed bundle suffix in administration asset path building in `\Shopware\Core\Framework\Api\Controller\InfoController` by removing bundle suffix
