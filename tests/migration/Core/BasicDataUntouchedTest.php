<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1536233560BasicData;

/**
 * @internal
 *
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
            'd9f207e8a9f72e831b2a0f495bfa2d86094da0f3',
            sha1_file($file),
            'BasicData migration has changed. This is not allowed.'
        );
    }
}
