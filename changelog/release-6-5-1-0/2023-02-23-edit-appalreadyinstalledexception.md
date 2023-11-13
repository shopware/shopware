---
title: Edit App Already installed Exception
issue: NEXT-19982
author: t.goldbach@shopware.com
---
# Core
* Changed `AppAlreadyInstalledException` class now extends `ShopwareHttpException` instead of `\Exception`.
* Added `getErrorCode` method to `AppAlreadyInstalledException` class.
* Added `getStatusCode` method to `AppAlreadyInstalledException` class.
* Changed `AppAlreadyInstalledException` class constructor message now uses the message template and placeholders.

