---
title: Add cancelable event to csrf handler
issue: NEXT-22774
---

# Storefront
* Changed return type of `NativeEventEmitter.publish` to return the fired event
* Added a third parameter to `NativeEventEmitter.publish` to allow for canceling the event
* Changed `beforeSubmit` event in `form-csrf-handler` plugin to be cancelable

