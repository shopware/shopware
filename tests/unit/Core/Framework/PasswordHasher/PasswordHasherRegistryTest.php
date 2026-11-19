<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\PasswordHasher;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\PasswordHasher\NativePasswordHasher;
use Shopware\Core\Framework\PasswordHasher\PasswordHasherException;
use Shopware\Core\Framework\PasswordHasher\PasswordHasherRegistry;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PasswordHasherRegistry::class)]
class PasswordHasherRegistryTest extends TestCase
{
    public function testReturnsHasherOfScope(): void
    {
        $customerHasher = new NativePasswordHasher();
        $adminHasher = new NativePasswordHasher();
        $registry = new PasswordHasherRegistry([
            PasswordField::FOR_CUSTOMER => $customerHasher,
            PasswordField::FOR_ADMIN => $adminHasher,
        ]);

        static::assertTrue($registry->has(PasswordField::FOR_CUSTOMER));
        static::assertSame($customerHasher, $registry->get(PasswordField::FOR_CUSTOMER));
        static::assertSame($adminHasher, $registry->get(PasswordField::FOR_ADMIN));
    }

    public function testThrowsForUnknownScope(): void
    {
        $registry = new PasswordHasherRegistry([PasswordField::FOR_CUSTOMER => new NativePasswordHasher()]);

        static::assertFalse($registry->has('integration'));

        $this->expectExceptionObject(PasswordHasherException::unknownScope('integration', [PasswordField::FOR_CUSTOMER]));

        $registry->get('integration');
    }
}
