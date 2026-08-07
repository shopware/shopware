---
title: Only evaluate sw-expect-packages header on authenticated routes
issue:
---
# API
* Changed `ExpectationSubscriber` to no longer evaluate the `sw-expect-packages` header on routes that do not require authentication, because the failure messages disclose installed package versions. Sending the header to such an endpoint now returns `417` with the new error code `FRAMEWORK__API_EXPECTATION_NOT_SUPPORTED` instead of evaluating the constraint. The behaviour on authenticated Admin API requests is unchanged.
