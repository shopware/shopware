<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Plugin\BundleConfigGenerator;
use Shopware\Core\Framework\Plugin\PluginException;
use Shopware\Core\Kernel;

/**
 * @internal
 */
#[CoversClass(BundleConfigGenerator::class)]
class BundleConfigGeneratorTest extends TestCase
{
    public function testConstructorThrowsException(): void
    {
        $this->expectException(PluginException::class);
        $this->expectExceptionMessage('Container parameter "kernel.project_dir" needs to be of type "string"');
        new BundleConfigGenerator(
            $this->createMock(Kernel::class),
            $this->createMock(ActiveAppsLoader::class)
        );
    }
}
