<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Validation\AbstractManifestValidator;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\App\Validation\Error\IncompatibleAppError;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ManifestValidator::class)]
class ManifestValidatorTest extends TestCase
{
    public function testValidateReturnsEveryError(): void
    {
        $incompatible = static::createStub(AbstractManifestValidator::class);
        $incompatible->method('validate')->willReturn([new IncompatibleAppError('test')]);

        $badName = static::createStub(AbstractManifestValidator::class);
        $badName->method('validate')->willReturn([new AppNameError('test')]);

        $result = (new ManifestValidator([$incompatible, $badName]))
            ->validate(ManifestFixture::empty(), Context::createDefaultContext());

        static::assertFalse($result->isOk());
        static::assertCount(2, $result->errors);
        static::assertInstanceOf(IncompatibleAppError::class, $result->errors[0]);
        static::assertInstanceOf(AppNameError::class, $result->errors[1]);
    }

    public function testValidateRunsEveryValidator(): void
    {
        $incompatible = static::createStub(AbstractManifestValidator::class);
        $incompatible->method('validate')->willReturn([new IncompatibleAppError('test')]);

        $later = $this->createMock(AbstractManifestValidator::class);
        $later->expects($this->once())->method('validate')->willReturn([]);

        (new ManifestValidator([$incompatible, $later]))
            ->validate(ManifestFixture::empty(), Context::createDefaultContext());
    }

    public function testValidateReturnsOkWhenNoValidatorReportsAnything(): void
    {
        $valid = static::createStub(AbstractManifestValidator::class);
        $valid->method('validate')->willReturn([]);

        $result = (new ManifestValidator([$valid]))
            ->validate(ManifestFixture::empty(), Context::createDefaultContext());

        static::assertTrue($result->isOk());
        static::assertNull($result->errors);
    }
}
