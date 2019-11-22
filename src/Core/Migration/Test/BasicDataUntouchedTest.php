<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class BasicDataUntouchedTest extends TestCase
{
    use KernelTestBehaviour;

    public function testBasicDataUntouched(): void
    {
        static::assertSame(
            '3e7e71398a6fc04a2dca7a9e114ef42914a50efe',
            sha1_file(TEST_PROJECT_DIR . '/platform/src/Core/Migration/Migration1536233560BasicData.php'),
            'BasicData migration has changed. This is not allowed.'
        );
    }
}
