<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\Migration1536233560BasicData;

class BasicDataUntouchedTest extends TestCase
{
    use KernelTestBehaviour;

    public function testBasicDataUntouched(): void
    {
        $loader = KernelLifecycleManager::getClassLoader();
        $file = $loader->findFile(Migration1536233560BasicData::class);

        static::assertSame(
            'fe89e1aff33290fa9e2afa71c0b09b0c518422ad',
            sha1_file($file),
            'BasicData migration has changed. This is not allowed.'
        );
    }
}
