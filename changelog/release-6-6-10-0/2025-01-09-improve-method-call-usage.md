---
title: Improve method call usage
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Checkout\Customer\Event\CustomerDeletedEvent` by adding `CustomerAware` to expose the `getCustomerId` method
* Changed several files to reduce the use of method calls by directly accessing the needed fields
