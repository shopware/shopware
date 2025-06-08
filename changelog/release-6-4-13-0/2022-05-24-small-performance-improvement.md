---
title: Small performance improvement
issue: NEXT-21758
author: Sebastian
author_email: 24sebastian05@gmail.com
author_github: sschwei1
---
# Storefront
* Changed `Shopware\Storefront\Controller\ContextController::switchLanguage()` to use `'[]'` instead of `json_encode([])` as default when calling `Symfony\Component\HttpFoundation\InputBag::get()` 
