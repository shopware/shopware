---
title: Guest document downloads are rate limited
issue: #304
---
# Core
* Changed guest document download requests using a deep link code to be covered by the guest login rate limiter. Repeated invalid authentication attempts are rejected once the configured limit is reached; a successful authentication resets the limit.
