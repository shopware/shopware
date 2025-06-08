---
title: Check for null in tax detector
issue: NEXT-20771
author: Moritz Pietzschke
author_email: mp@mopie.de
author_github: pietzschke
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Tax\TaxDetector` to filter out `null` values in VAT-ID list and prevent a TypeError
