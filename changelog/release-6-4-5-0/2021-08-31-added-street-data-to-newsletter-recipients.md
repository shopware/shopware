---
title: Added street data to newsletter recipients
issue: NEXT-16976
author: Malte Janz
author_email: m.janz@shopware.com 
author_github: Malte Janz
---
# Core
* Changed `hydrateFromCustomer` method in `NewsletterController` to also add the street to the data bag
* Changed `hydrateFromCustomer` method in `NewsletterAccountPageletLoader` to also add the street to the data bag
* Added assertions to check the recipient data in `NewsletterControllerTest`
