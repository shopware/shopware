<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Field;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Field\PasswordField;

/**
 * @internal
 */
#[CoversClass(PasswordField::class)]
class PasswordFieldTest extends TestCase
{
    public function testInstantiate(): void
    {
        $field = new PasswordField(
            'custom_password',
            'customPassword',
            \PASSWORD_DEFAULT,
            ['b'],
            PasswordField::FOR_ADMIN
        );

        static::assertSame('custom_password', $field->getStorageName());
        static::assertSame('customPassword', $field->getPropertyName());
        static::assertSame(\PASSWORD_DEFAULT, $field->getAlgorithm());
        static::assertSame(['b'], $field->getHashOptions());
        static::assertSame('admin', $field->getFor());
    }
}
