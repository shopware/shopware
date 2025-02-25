<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Parameters;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Lifecycle\Parameters\AppInstallParameters;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AppInstallParameters::class)]
class AppInstallParametersTest extends TestCase
{
    public function testAccessors(): void
    {
        $options = new AppInstallParameters();

        static::assertTrue($options->activate);
        static::assertTrue($options->acceptPermissions);

        $options = new AppInstallParameters(false, false);

        static::assertFalse($options->activate);
        static::assertFalse($options->acceptPermissions);
    }
}
