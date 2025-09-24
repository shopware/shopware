---
title: Improve error output of app loader in CI environment
author: Michael Telgmann
author_github: @mitelg
---

# Core

* Added `\Shopware\Core\Framework\Util\IOStreamHelper` to centralize IO stream handling.
* Changed error output of `\Shopware\Core\Framework\App\ActiveAppsLoader::loadApps` in CI environment to show a less urgent error message.
