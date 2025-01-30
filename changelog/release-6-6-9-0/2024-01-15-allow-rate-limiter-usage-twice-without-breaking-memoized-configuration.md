---
title: Allow rate limiter usage twice without breaking memoized rate limit configuration
issue: NEXT-35068
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed usage of in-memory config in `\Shopware\Core\Framework\RateLimiter\RateLimiterFactory` to ensure multiple usages without breaking configuration
