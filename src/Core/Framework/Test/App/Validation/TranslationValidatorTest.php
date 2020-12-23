<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\TranslationValidator;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class TranslationValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var TranslationValidator
     */
    private $translationValidator;

    public function setUp(): void
    {
        $this->translationValidator = $this->getContainer()->get(TranslationValidator::class);
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $violations = $this->translationValidator->validate($manifest, null);
        static::assertCount(0, $violations->getElements());
    }

    public function testValidateReturnsErrorCollectionIfTranslationValidationsExists(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidTranslations/manifest.xml');

        $violations = $this->translationValidator->validate($manifest, null);
        static::assertEquals('Missing translations for "Metadata":
- label: de-DE, fr-FR', $violations->first()->getMessage());
    }
}
