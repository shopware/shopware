<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\AppNameValidator;
use Shopware\Core\Framework\App\Validation\Error\AppNameError;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class AppNameValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var AppNameValidator
     */
    private $appNameValidator;

    public function setUp(): void
    {
        $this->appNameValidator = $this->getContainer()->get(AppNameValidator::class);
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

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
