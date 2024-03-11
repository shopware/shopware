---
title: Add possibility to add local manifest file
issue: NEXT-34166
---
# Core
* Changed `\Shopware\Core\Framework\App\Lifecycle\AppLoader::load` to allow loading a local manifest file. This is useful for development and testing purposes.
___
# Upgrade Information
## Local app manifest

In app's development, it's usually necessary to have a different configuration or urls in the manifest file. For e.g, on the production app, the manifest file should have the production endpoints and the setup's secret should not be set, in development, we can set a secret and use local environment endpoints.

This change allows you to create a local manifest file that overriding the real's manifest.

All you have to do is create a `manifest.local.xml` and place it in the root of the app's directory. 

_Hint: The local manifest file should be ignored on the actual app's repository_