---
title: Handling incident rate-limit
issue: NEXT-29596
---
# Core
* Added new static methods `newsletterThrottled` into domain exception class `\Shopware\Core\Content\Newsletter\NewsletterException` for throttling.
* Changed method `subscribe` in `Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute` to try-catch `RateLimitExceededException`
