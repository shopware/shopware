<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DependencyInjectionException::class)]
class DependencyInjectionExceptionTest extends TestCase
{
    public function testProjectDirNotInContainer(): void
    {
        $this->expectExceptionObject(DependencyInjectionException::projectDirNotInContainer());

        throw DependencyInjectionException::projectDirNotInContainer();
    }

    public function testBundlesMetadataIsNotAnArray(): void
    {
        $this->expectExceptionObject(DependencyInjectionException::bundlesMetadataIsNotAnArray());

        throw DependencyInjectionException::bundlesMetadataIsNotAnArray();
    }
}
