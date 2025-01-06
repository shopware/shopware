---
title: Deprecate unused auth endpoint
issue: NEXT-38794
---
# API
* Deprecated `\Core\Framework\Api\Controller\AuthController::authorize` method (API route `/api/oauth/authorize`). It will be removed without replacement with the next major version.

___
# Next Major Version Changes
* Removed `\Core\Framework\Api\Controller\AuthController::authorize` method (API route `/api/oauth/authorize`) without replacement.
