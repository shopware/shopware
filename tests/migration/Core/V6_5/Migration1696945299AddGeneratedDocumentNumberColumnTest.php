<?php
/*
 * Copyright (c) Pickware GmbH. All rights reserved.
 * This file is part of software that is released under a proprietary license.
 * You must not copy, modify, distribute, make publicly available, or execute
 * its contents or parts thereof without express permission by the copyright
 * holder, unless otherwise permitted by law.
 */

declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1696945299AddGeneratedDocumentNumberColumn;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1696945299AddGeneratedDocumentNumberColumn
 */
class Migration1696945299AddGeneratedDocumentNumberColumnTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Connection $connection;

    private Migration1696945299AddGeneratedDocumentNumberColumn $migration;

    protected function setUp(): void
    {
        $this->migration = new Migration1696945299AddGeneratedDocumentNumberColumn();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1696945299', $this->migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->migration->update($this->connection);

        static::assertStringContainsString(
            '`document_number` varchar(255)',
            $this->getSchema(),
        );

        static::assertStringContainsString(
            '`idx.document.document_number` (`document_number`)',
            $this->getSchema(),
        );
    }

    public function testUpdateTwice(): void
    {
        $this->migration->update($this->connection);

        static::assertStringContainsString(
            '`document_number` varchar(255)',
            $this->getSchema(),
        );

        static::assertStringContainsString(
            '`idx.document.document_number` (`document_number`)',
            $this->getSchema(),
        );

        $expected = $this->getSchema();

        $this->migration->update($this->connection);
        static::assertSame($expected, $this->getSchema());
    }

    /**
     * @throws \Throwable
     */
    private function getSchema(): string
    {
        $schema = $this->connection->fetchAssociative(sprintf('SHOW CREATE TABLE `%s`', 'document'));
        static::assertNotFalse($schema);
        static::assertIsString($schema['Create Table']);

        return $schema['Create Table'];
    }
}
