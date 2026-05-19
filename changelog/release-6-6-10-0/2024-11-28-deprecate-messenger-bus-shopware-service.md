---
title: Deprecate messenger.bus.shopware service
issue: NEXT-39755
---
# Core
* Deprecated the `messenger.bus.shopware` service. The functionality provided by our decorator has been moved to middleware so you can safely use `messenger.default_bus` instead.
___
# Upgrade Information
## Deprecated `messenger.bus.shopware` service
Change your usages of `messenger.bus.shopware` to `messenger.default_bus`. As long as you typed the interface `\Symfony\Component\Messenger\MessageBusInterface`, your code will work as expected.

___
# Next Major Version Changes
## Removed `messenger.bus.shopware` service
Use `messenger.default_bus` instead.
