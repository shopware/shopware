---
title: Fix MessengerMiddlewareCompilerPass
issue: #6911
---
# Core
* Changed `\Shopware\Core\Framework\DependencyInjection\CompilerPass\MessengerMiddlewareCompilerPass` to handle cases when middlewares are not defined yet
___
# Upgrade Information
## Fix `MessengerMiddlewareCompilerPass` middleware assertion

The `MessengerMiddlewareCompilerPass` now handles cases when middlewares are not defined yet. This change ensures that the middleware is correctly registered in the application.
