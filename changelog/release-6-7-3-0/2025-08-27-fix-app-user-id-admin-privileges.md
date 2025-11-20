---
title: Fix app user id admin privileges

author_email: e.imamoglu@shopware.com
author_github: emreimamoglu
---
# API
* Changed `\Shopware\Core\Framework\Routing\ApiRequestContextResolver::getAdminApiSource` to skip intersection process if the caller is admin.
