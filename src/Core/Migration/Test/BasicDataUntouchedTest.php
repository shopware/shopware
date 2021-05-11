<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_3\Migration1536233560BasicData;

class BasicDataUntouchedTest extends TestCase
{
    use KernelTestBehaviour;

    public function testBasicDataUntouched(): void
    {
        $loader = KernelLifecycleManager::getClassLoader();
        $file = $loader->findFile(Migration1536233560BasicData::class);

        static::assertSame(
            'b11616e6ed6df5cf0bfeb48d63f857be7de74317',
            sha1_file($file),
            'BasicData migration has changed. This is not allowed.'
        );
    }
}
