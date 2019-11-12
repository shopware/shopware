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
            '26b1b1e446693e9e37ff64b36c8b7c3248e3bbb3',
            sha1_file(TEST_PROJECT_DIR . '/platform/src/Core/Migration/Migration1536233560BasicData.php'),
            'BasicData migration has changed. This is not allowed.'
        );
    }
}
