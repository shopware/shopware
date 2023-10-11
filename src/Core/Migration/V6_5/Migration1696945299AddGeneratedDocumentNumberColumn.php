<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Core\Migration\V6_5;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
#[Package('core')]
class Migration1696945299AddGeneratedDocumentNumberColumn extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1696945299;
    }

    public function update(Connection $connection): void
    {
        $columns = $connection->executeQuery('
            SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns
                WHERE table_schema = :database
                  AND table_name = \'document\'
                  AND COLUMN_NAME = \'document_number\';
        ', ['database' => $connection->getDatabase()])->fetchAllAssociativeIndexed();

        if (!\array_key_exists('document_number', $columns)) {
            $connection->executeStatement('
                ALTER TABLE `document`
                ADD COLUMN `document_number` VARCHAR(255)
                GENERATED ALWAYS AS (
                    COALESCE(JSON_UNQUOTE(JSON_EXTRACT(`config`, \'$.documentNumber\')))
                ) STORED;
            ');
        }
        $indexes = $connection->executeQuery('
            SELECT INDEX_NAME FROM information_schema.STATISTICS
                WHERE table_schema = :database
                  AND table_name = \'document\'
                  AND COLUMN_NAME = \'document_number\'
        ', ['database' => $connection->getDatabase()])->fetchFirstColumn();

        if (!\in_array('idx.document.document_number', $indexes, true)) {
            $connection->executeStatement('
                CREATE INDEX `idx.document.document_number`
                    ON `document` (`document_number`);
            ');
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
