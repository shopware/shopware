<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Parameters;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppUpdateParameters;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppUpdateParameters::class)]
class AppUpdateParametersTest extends TestCase
{
    public function testAccessors(): void
    {
        $options = new AppUpdateParameters();

        static::assertTrue($options->acceptPermissions);

        $options = new AppUpdateParameters(false);

        static::assertFalse($options->acceptPermissions);
    }
}
