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
            '3d3bc7bf76a719a79b427ff0c1cad4009b44d634',
            sha1_file(TEST_PROJECT_DIR . '/platform/src/Core/Migration/Migration1536233560BasicData.php'),
            'BasicData migration has changed. This is not allowed.'
        );
    }
}
