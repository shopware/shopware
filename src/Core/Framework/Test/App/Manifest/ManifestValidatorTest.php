<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\App\Manifest;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\ManifestValidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Webhook\Exception\HookableValidationException;

class ManifestValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var ManifestValidator
     */
    private $manifestValidator;

    public function setUp(): void
    {
        $this->manifestValidator = $this->getContainer()->get(ManifestValidator::class);
    }

    public function testValidate(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/test/manifest.xml');

        $this->manifestValidator->validate($manifest, Context::createDefaultContext());
    }

    public function testValidateThrowsHookableValidationException(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../Manifest/_fixtures/invalidWebhooks/manifest.xml');

        static::expectException(HookableValidationException::class);
        $this->manifestValidator->validate($manifest, Context::createDefaultContext());
    }
}
