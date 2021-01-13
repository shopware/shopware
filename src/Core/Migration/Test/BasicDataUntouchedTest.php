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
            'b1a13ae960652d4563da0a6eef219f8942d7d1a9',
            sha1_file($file),
            'BasicData migration has changed. This is not allowed.'
        );
    }
}
