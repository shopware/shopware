---
title: Make Core mail templates independent from Storefront urls
date: 2021-09-06
area: storefront
tags: [mail, storefront, headless]
---

## Context
Some mail templates of the core component (Newsletter, Registration, Password Recovery, Order Status mails) depend on storefront Urls to be included in the mails.
Those Urls are not available when shopware is used in "headless" mode, without the storefront bundle being installed.

For some mails (Newsletter subscription, Double Opt-In, Password recovery), the Url was made configurable over the system config and over the settings inside the administration.  
The default values for those Urls are the ones that the storefront bundle would use.
This option does not really scale well as each Url that should be used, needs to be configurable in the administration and this can grow quickly out of hand.
Additionally, it is not clear when and where those configs should be used to generate the absolute Urls, as with the BusinessEvent system and the upcoming FlowBuilder, the sending of mails is not necessarily triggered by the same entry point all the times, but different trigger can lead to sending the same mails.

## Decision
There shouldn't be any links generated on PHP-side as that can be hard to override per sales-channel and can not easily be changed by apps, and links should be generated inside the mailTemplates with string concatenation instead of `raw_url`-twig functions, so the links can still be generated even if the route is not registered in the system.
To make generation of urls inside the mail templated easier, we will add a `{{ domain }}` variable to the twig context, that contains the domain of the current salesChannelContext, of the order in question etc.

The URLs we use in the core mail templates become part of the public API, and custom frontends should adhere to theme and provide routes under the same path, or create redirects so that the default URLs work for their frontend implementation.

The default urls are:
```
/account/order/{deepLinkCode} -> opens the order details of the given order
/account/recover/password?hash={recoverHash} -> start password recovery process
/newsletter-subscribe?em={emailHash}&hash={subscribeHash} -> Subscribe email with given hash to the newsletter (for douple-opt in)
/registration/confirm?em={emailHash}&hash={subscribeHash} -> Confirm registration for user eith the given mail hash (for douple-opt in)
```

If the custom frontends can't or don't want to use our default URLs they can use the possibility to override the existing mail templates to generate custom URLs.

We will deprecate the usage of the system-config configuration values and the events thrown when the links are generated on PHP-side and remove those in the next major version.
To be forward compatible we will already pass the necessary data needed for generating the links into the templates, so the urls can be already generated inside the mail templates.

Third party clients (like the PWA) should either adhere to our default URLs or add additional mail templates, that generate the correct urls for their client.
In addition to that the third party client could extend the core mail template, rather than providing a new one, and then deciding in an `IF/ELSE` what url needs to be generated based on the salesChannel or domain.

## Consequences
The core mail templates work independently from the storefront bundle.

The urls listed in this ADR will become public API, so we cannot easily change those URLs, but have to maintain them in a backward compatible manner.

To ensure this we will add a unit test, that verifies that in our default templates we don't use any `raw_url`-functions. Additionally we ensure that the `{{ domain }}` variable is present by an unit test.
As the Urls we use become public API, we will add a documentation article where we document the public Urls and to ensure that the documentation is up do date we will add another unit test.
