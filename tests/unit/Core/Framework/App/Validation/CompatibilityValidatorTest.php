<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Validation\CompatibilityValidator;
use Shopware\Core\Framework\App\Validation\Error\IncompatibleAppError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CompatibilityValidator::class)]
class CompatibilityValidatorTest extends TestCase
{
    public function testCompatibleAppProducesNoError(): void
    {
        $manifest = ManifestFixture::empty();
        $manifest->getMetadata()->assign(['compatibility' => '~6.5.0']);

        $errors = (new CompatibilityValidator('6.5.0.0'))->validate($manifest, null);

        static::assertCount(0, $errors);
    }

    public function testIncompatibleAppProducesAnError(): void
    {
        $manifest = ManifestFixture::empty();
        $manifest->getMetadata()->assign(['compatibility' => '~7.0.0']);

        $errors = (new CompatibilityValidator('6.5.0.0'))->validate($manifest, null);

        static::assertCount(1, $errors);
        $error = $errors[0];
        static::assertInstanceOf(IncompatibleAppError::class, $error);
        static::assertSame('App test is not compatible with this Shopware version', $error->getMessage());
    }
}
