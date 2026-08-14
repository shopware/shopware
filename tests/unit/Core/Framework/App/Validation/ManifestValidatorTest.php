<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
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
    public function testValidateReportsEveryError(): void
    {
        $incompatible = static::createStub(AbstractManifestValidator::class);
        $incompatible->method('validate')->willReturn([new IncompatibleAppError('test')]);

        $badName = static::createStub(AbstractManifestValidator::class);
        $badName->method('validate')->willReturn([new AppNameError('test')]);

        $validator = new ManifestValidator([$incompatible, $badName]);

        try {
            $validator->validate(ManifestFixture::empty(), Context::createDefaultContext());
            static::fail('expected the manifest to be refused');
        } catch (AppException $e) {
            static::assertStringContainsString('is not compatible with this Shopware version', $e->getMessage());
            static::assertStringContainsString('and the folder name must be equal', $e->getMessage());
        }
    }

    public function testThrowOnFirstErrorStopsAtTheFirstFailureAndKeepsItsErrorCode(): void
    {
        $incompatible = static::createStub(AbstractManifestValidator::class);
        $incompatible->method('validate')->willReturn([new IncompatibleAppError('test')]);

        $later = $this->createMock(AbstractManifestValidator::class);
        $later->expects($this->never())->method('validate');

        $validator = new ManifestValidator([$incompatible, $later]);

        try {
            $validator->throwOnFirstError(ManifestFixture::empty(), Context::createDefaultContext());
            static::fail('expected the manifest to be refused');
        } catch (AppException $e) {
            static::assertSame(AppException::NOT_COMPATIBLE, $e->getErrorCode());
            static::assertSame(['name' => 'test'], $e->getParameters());
        }
    }

    public function testThrowOnFirstErrorPassesAValidManifest(): void
    {
        $this->expectNotToPerformAssertions();

        $valid = static::createStub(AbstractManifestValidator::class);
        $valid->method('validate')->willReturn([]);

        (new ManifestValidator([$valid]))
            ->throwOnFirstError(ManifestFixture::empty(), Context::createDefaultContext());
    }
}
