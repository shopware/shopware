<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Validation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Exception\AppValidationException;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Validation\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
class ManifestValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private ManifestValidator $manifestValidator;

    public function setUp(): void
    {
        $this->manifestValidator = $this->getContainer()->get(ManifestValidator::class);
    }

    /**
     * Due to the deprecation of NewsletterUpdateEvent (in NewsletterEvents).
     * Only remove DisabledFeatures not the method when removing the event
     *
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $this->manifestValidator->validate($manifest, Context::createDefaultContext());
    }

    /**
     * Due to the deprecation of NewsletterUpdateEvent (in NewsletterEvents).
     * Only remove DisabledFeatures not the method when removing the event
     *
     * @DisabledFeatures(features={"v6.5.0.0"})
     */
    public function testValidateInvalidManifest(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidManifest/manifest.xml');

        static::expectException(AppValidationException::class);
        static::expectExceptionMessage('The app "invalidName" is invalid');
        static::expectExceptionMessage('Missing translations for "Metadata":');
        static::expectExceptionMessage('The technical app name "invalidName" in the "manifest.xml" and the folder name must be equal.');
        static::expectExceptionMessage('The following custom components are not allowed to be used in app configuration:');
        static::expectExceptionMessage('The following webhooks are not hookable:');
        static::expectExceptionMessage('The following permissions are missing:');
        $this->manifestValidator->validate($manifest, Context::createDefaultContext());
    }
}
