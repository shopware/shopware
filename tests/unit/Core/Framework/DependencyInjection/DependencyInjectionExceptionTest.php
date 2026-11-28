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

    public function testInvalidPasswordHasherOptions(): void
    {
        $error = new \ValueError('Invalid bcrypt cost parameter specified: 99');

        $exception = DependencyInjectionException::invalidPasswordHasherOptions('bcrypt', $error);

        static::assertSame('FRAMEWORK__INVALID_PASSWORD_HASHER_OPTIONS', $exception->getErrorCode());
        static::assertSame('Invalid options for algorithm "bcrypt": Invalid bcrypt cost parameter specified: 99', $exception->getMessage());
        static::assertSame($error, $exception->getPrevious());
    }
}
