<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\EnvTestBehaviour;
use Shopware\Core\TestBootstrapper;

/**
 * @internal
 */
#[CoversClass(TestBootstrapper::class)]
class TestBootstrapperTest extends TestCase
{
    use EnvTestBehaviour;

    public function testGetDatabaseUrlWithoutSuffix(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'mysql://root:root@localhost:3306/test',
        ]);

        $testBootstrapper = new TestBootstrapper();
        static::assertSame('mysql://root:root@localhost:3306/test_test', $testBootstrapper->getDatabaseUrl());

        $this->resetEnvVars();
    }

    public function testGetDatabaseUrlWithSuffix(): void
    {
        $this->setEnvVars([
            'DATABASE_URL' => 'mysql://root:root@localhost:3306/test_test',
        ]);

        $testBootstrapper = new TestBootstrapper();
        static::assertSame('mysql://root:root@localhost:3306/test_test', $testBootstrapper->getDatabaseUrl());

        $this->resetEnvVars();
    }

    public function testGetDatabaseUrlAlreadySet(): void
    {
        $testBootstrapper = new TestBootstrapper();
        $testBootstrapper->setDatabaseUrl('test');

        static::assertSame('test', $testBootstrapper->getDatabaseUrl());
    }
}
