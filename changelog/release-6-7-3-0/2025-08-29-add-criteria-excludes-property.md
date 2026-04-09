---
title: Add criteria `excludes` property
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Added `excludes` property with getter & setter to `Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria`
* Changed `Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder` to correctly handle the new criteria `excludes` property
* Changed `Shopware\Core\System\SalesChannel\Api\ResponseFields` to correctly handle the new criteria `excludes` property
