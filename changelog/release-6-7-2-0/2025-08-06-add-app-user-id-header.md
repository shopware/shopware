---
title: Add app user ID header and fix domain exception patterns
issue: #11608

author_email: s.vorgers@shopware.com
author_github: SimonVorgers
---
# API
* Added `\Shopware\Core\PlatformRequest::HEADER_APP_USER_ID`
* Added new app user ID header support in `\Shopware\Core\Framework\Routing\ApiRequestContextResolver` for API requests from apps to run in the context of the app user
