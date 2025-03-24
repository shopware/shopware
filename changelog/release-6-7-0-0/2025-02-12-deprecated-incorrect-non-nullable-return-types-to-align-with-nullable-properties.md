---
title: Deprecated incorrect non-nullable return types to align with nullable properties
issue: NEXT-40590
author: Martin Krzykawski
author_email: m.krzykawski@shopware.com
author_github: @MartinKrzykawski
---
# Core
* Deprecated `Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeEntity::getCoverUrl`. Return type will be nullable and condition will be removed in v6.8.0.
* Deprecated `Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeEntity::getIconName`. Return type will be nullable and condition will be removed in v6.8.0.
* Deprecated `Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeEntity::getScreenshotUrls`. Return type will be nullable and condition will be removed in v6.8.0.
* Deprecated `Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity::getMedia`. Return type will be nullable and condition will be removed in v6.8.0.
