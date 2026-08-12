<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\AbstractManifestValidator;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
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
        $validator = new ManifestValidator([
            new StaticValidator(new IncompatibleAppError('test')),
            new StaticValidator(new AppNameError('test')),
        ]);

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
        $later = new StaticValidator();
        $validator = new ManifestValidator([new StaticValidator(new IncompatibleAppError('test')), $later]);

        try {
            $validator->throwOnFirstError(ManifestFixture::empty(), Context::createDefaultContext());
            static::fail('expected the manifest to be refused');
        } catch (AppException $e) {
            static::assertSame(AppException::NOT_COMPATIBLE, $e->getErrorCode());
            static::assertSame(['name' => 'test'], $e->getParameters());
        }

        static::assertSame(0, $later->calls);
    }

    public function testThrowOnFirstErrorPassesAValidManifest(): void
    {
        $this->expectNotToPerformAssertions();

        (new ManifestValidator([new StaticValidator()]))
            ->throwOnFirstError(ManifestFixture::empty(), Context::createDefaultContext());
    }
}

/**
 * @internal
 */
#[Package('framework')]
class StaticValidator extends AbstractManifestValidator
{
    public int $calls = 0;

    public function __construct(private readonly ?Error $error = null)
    {
    }

    public function validate(Manifest $manifest, ?Context $context): ErrorCollection
    {
        ++$this->calls;

        return new ErrorCollection($this->error ? [$this->error] : []);
    }
}
