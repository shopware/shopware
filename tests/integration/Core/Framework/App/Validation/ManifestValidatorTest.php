<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppValidationException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\App\Validation\Error\ConfigurationError;
use Shopware\Core\Framework\App\Validation\Error\ErrorCollection;
use Shopware\Core\Framework\App\Validation\Error\MissingPermissionError;
use Shopware\Core\Framework\App\Validation\Error\MissingTranslationError;
use Shopware\Core\Framework\App\Validation\Error\NotHookableError;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class ManifestValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ManifestValidator $manifestValidator;

    protected function setUp(): void
    {
        $this->manifestValidator = static::getContainer()->get(ManifestValidator::class);
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $error = null;
        $message = '';
        try {
            $this->manifestValidator->validate($manifest, Context::createDefaultContext());
        } catch (\Throwable $e) {
            $error = $e;
            $message = \sprintf('No error expected, got "%s" with: %s', $error->getMessage(), $error->getTraceAsString());
        }
        static::assertNull($error, $message);
    }

    #[DataProvider('invalidManifestProvider')]
    public function testValidateInvalidManifest(AppValidationException $expectedException): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidManifest/manifest.xml');

        $this->expectExceptionObject($expectedException);
        $this->manifestValidator->validate($manifest, Context::createDefaultContext());
    }

    public static function invalidManifestProvider(): \Generator
    {
        yield 'app prefix' => [new AppValidationException('invalidManifestName', new ErrorCollection())];
        yield 'missing translations' => [new AppValidationException('invalidManifestName', new ErrorCollection([new MissingTranslationError(Metadata::class, [])]))];
        yield 'app name mismatch' => [new AppValidationException('invalidManifestName', new ErrorCollection([new AppNameError('invalidManifestName')]))];
        yield 'configuration error' => [new AppValidationException('invalidManifestName', new ErrorCollection([new ConfigurationError([])]))];
        yield 'not hookable webhooks' => [new AppValidationException('invalidManifestName', new ErrorCollection([new NotHookableError([])]))];
        yield 'missing permissions' => [new AppValidationException('invalidManifestName', new ErrorCollection([new MissingPermissionError([])]))];
    }
}
