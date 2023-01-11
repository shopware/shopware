---
title: Fix typo in API expectation error code
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Changed constant return value of `\Shopware\Core\Framework\Api\Exception\ExceptionFailedException::getErrorCode` from `FRAMEWORK__API_EXCEPTION_FAILED` to `FRAMEWORK__API_EXPECTATION_FAILED`
