<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Attribute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Attribute\Password;

/**
 * @internal
 */
#[CoversClass(Password::class)]
class PasswordTest extends TestCase
{
    public function testDefaults(): void
    {
        $password = new Password();

        static::assertSame('password', $password->type);
        static::assertSame(\PASSWORD_DEFAULT, $password->algorithm);
        static::assertSame([], $password->hashOptions);
        static::assertNull($password->for);
        static::assertFalse($password->api);
        static::assertNull($password->column);
    }

    public function testCustomValues(): void
    {
        $password = new Password(
            algorithm: \PASSWORD_BCRYPT,
            hashOptions: ['cost' => 12],
            for: 'customer',
            api: true,
            column: 'custom_password',
        );

        static::assertSame('password', $password->type);
        static::assertSame(\PASSWORD_BCRYPT, $password->algorithm);
        static::assertSame(['cost' => 12], $password->hashOptions);
        static::assertSame('customer', $password->for);
        static::assertTrue($password->api);
        static::assertSame('custom_password', $password->column);
    }
}
