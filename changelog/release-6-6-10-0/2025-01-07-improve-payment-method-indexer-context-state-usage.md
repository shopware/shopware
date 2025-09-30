---
title: Improve PaymentMethodIndexer context state usage
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexer` by using the context's `state` function to temporarily add the `disable-indexing` state for payment method upsert
