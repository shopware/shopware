<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\AppNameValidator;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;

/**
 * @internal
 */
#[CoversClass(AppNameValidator::class)]
class AppNameValidatorTest extends TestCase
{
    private AppNameValidator $appNameValidator;

    protected function setUp(): void
    {
        $this->appNameValidator = new AppNameValidator();
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $violations = $this->appNameValidator->validate($manifest, null);
        static::assertCount(0, $violations->getElements());
    }

    public function testValidateNonCaseSensitive(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');
        $manifest->getMetadata()->assign(['name' => 'TeSt']);

        $violations = $this->appNameValidator->validate($manifest, null);
        static::assertCount(0, $violations->getElements());
    }

    public function testValidateReturnsErrors(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidAppName/manifest.xml');

        $violations = $this->appNameValidator->validate($manifest, null);

        static::assertCount(1, $violations->getElements());
        static::assertInstanceOf(AppNameError::class, $violations->first());
        static::assertStringContainsString('The technical app name "notSameAppNameAsFolder" in the "manifest.xml" and the folder name must be equal.', $violations->first()->getMessage());
    }
}
