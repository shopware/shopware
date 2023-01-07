<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1536233560BasicData;

/**
 * @internal
 * @coversNothing
 */
class BasicDataUntouchedTest extends TestCase
{
    public function testBasicDataUntouched(): void
    {
        $loader = KernelLifecycleManager::getClassLoader();
        /** @var string $file */
        $file = $loader->findFile(Migration1536233560BasicData::class);

        static::assertSame(
            'be0c21bc1d7d0be69cf6c15ea3981f37c73c74be',
            sha1_file($file),
            'BasicData migration has changed. This is not allowed.'
        );
    }
}
