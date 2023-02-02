---
title: Reactivate plugin if the deactivation fails
issue: NEXT-22798
---
# Core
* Added a new event `Shopware\Core\Framework\Plugin\Event\PluginPostDeactivationFailedEvent.php` to be fired if the deactivation of the plugin fails after the `PluginPreDeactivateEvent` was handled.

___
# Storefront
* Changed `PluginLifecycleSubscriber.php` to run the activation workflow (`pluginPostActivate`) if the plugin deactivation failed.
