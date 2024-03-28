---
title: Allow rate limiter usage twice without breaking memoized rate limit configuration
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed usage of in-memory config in `\Shopware\Core\Framework\RateLimiter\RateLimiterFactory` to ensure multiple use without breaking configuration
