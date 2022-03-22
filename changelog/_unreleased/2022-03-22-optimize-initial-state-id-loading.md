---
title: Optimize initial state id loading
issue: NEXT-20687
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
author_github: OliverSkroblin
---
# Core
* Deprecated `\Shopware\Core\System\StateMachine\StateMachineRegistry::getInitialState`, use `\Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader::get` instead
* Added twig cache in `\Shopware\Core\Framework\Adapter\Twig\StringTemplateRenderer` to avoid unnecessary template parsing for mail rendering and other templates. The cache key is built with the content of the provided template.