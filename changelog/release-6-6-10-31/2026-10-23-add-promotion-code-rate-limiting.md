---
title: Add configurable promotion code rate limiting
issue: 21673
---

# API

* Added `shopware.api.rate_limiter.promotion_code` to configure the separate rate limit shared by Store API and Storefront promotion code submissions.
  Submitting a different code does not start a new allowance.
  Store API clients receive HTTP 429 when throttled, while Storefront shoppers receive a message with the waiting time.
