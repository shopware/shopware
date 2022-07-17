---
title: Fix scheduled comparison sales channel generation with a huge amount of data
issue: NEXT-22268
author: Vladislav Borisenko
author_email: yourborjia@gmail.com
author_github: yourborjia
---
# Framework
* Added new filesystem class `Shopware\Core\Framework\Adapter\Filesystem\WriteAppend\AppendFilesystem` with an interface `Shopware\Core\Framework\Adapter\Filesystem\WriteAppend\AppendFilesystemInterface` to add possibility to write files with append.
* Added new filesystem type `shopware.filesystem.private.appendable` with factory `Shopware\Core\Framework\Adapter\Filesystem\WriteAppend\AppendFilesystemFactory`.
# Core
* Added `Shopware\Core\Content\ProductExport\Struct\Specification\JobStuckSpecification` to check is export job process was interrupted by OS or system fault to make possible to recover export process.
* Added new DI parameter`%product_export.stuck_job_spec.idle_timeout%` to make possible to configure different export idle timeout (default - 15min) used in `Shopware\Core\Content\ProductExport\Struct\Specification\JobStuckSpecification`
* Changed `\Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTaskHandler::run` to check is export currently running and as result prevent concurrent generation.
* Changed `\Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportPartialGenerationHandler::handle` to update export entity's "updatedAt" field on each export part generation.
