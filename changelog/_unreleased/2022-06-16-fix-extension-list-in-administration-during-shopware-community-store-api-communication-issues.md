---
title: Fix extension list in administration during Shopware Community Store API communication issues
issue: NEXT-22054
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added exception catching in `\Shopware\Core\Framework\Store\Services\StoreClient` that previously blocked administration extension listing when checking for updates
