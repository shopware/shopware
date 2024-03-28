---
title: Move max upload filesize logic
issue: NEXT-32087
author_github: @Dominik28111
---
# Core
* Added method `Shopware\Core\Framework\Util\MemorySizeCalculator::getMaxUploadSize()` to calculate the maximum upload size.
* Changed method `Shopware\Core\Content\ImportExport\Service\SupportedFeaturesService::getUploadFileSizeLimit()` to use the new method `Shopware\Core\Framework\Util\MemorySizeCalculator::getMaxUploadSize()`.
