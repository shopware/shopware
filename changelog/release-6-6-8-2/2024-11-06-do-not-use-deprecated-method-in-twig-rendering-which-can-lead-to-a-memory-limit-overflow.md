---
title: Do not use deprecated method in Twig rendering which can lead to a memory limit overflow
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Storefront
* Changed `Shopware\Storefront\Theme\ThemeConfigValueAccessor::get` to not use the deprecated method `Shopware\Storefront\Theme\ThemeConfigValueAccessor::buildName` as the trace in Twig rendering can become quite large which can easily lead to a memory limit overflow in `Symfony\Component\ErrorHandler\ErrorHandler::cleanTrace`, as the stack traces in Twig rendering can become quite large
