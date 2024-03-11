---
title: Deprecate CreateSchemaCommand and SchemaGenerator 
issue: NEXT-33257
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
---
# Core
* Deprecated `\Shopware\Core\Framework\DataAbstractionLayer\Command\CreateSchemaCommand` and `\Shopware\Core\Framework\DataAbstractionLayer\SchemaGenerator`, use `\Shopware\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand` and `\Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator` instead
___
# Next Major Version Changes

## \Shopware\Core\Framework\DataAbstractionLayer\Command\CreateSchemaCommand:
`\Shopware\Core\Framework\DataAbstractionLayer\Command\CreateSchemaCommand` will be removed. You can use `\Shopware\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand` instead.

## \Shopware\Core\Framework\DataAbstractionLayer\SchemaGenerator:
`\Shopware\Core\Framework\DataAbstractionLayer\SchemaGenerator` will be removed. You can use `\Shopware\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator` instead.
