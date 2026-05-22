<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppFeatureValidator;
use Shopware\Tests\Unit\Core\Framework\App\Manifest\ManifestFixture;

/**
 * @internal
 */
#[CoversClass(AppFeatureValidator::class)]
class AppFeatureValidatorTest extends TestCase
{
    public function testDoesNotThrowWhenAppSecretExists(): void
    {
        $validator = new AppFeatureValidator('dev');

        $validator->validate($this->createApp('secret'), $this->createManifestWithFeaturesRequiringSecret());

        $this->addToAssertionCount(1);
    }

    public function testDoesNotThrowOutsideDevEnvironment(): void
    {
        $validator = new AppFeatureValidator('prod');

        $validator->validate($this->createApp(), $this->createManifestWithFeaturesRequiringSecret());

        $this->addToAssertionCount(1);
    }

    public function testDoesNotThrowWithoutApplicableFeatures(): void
    {
        $validator = new AppFeatureValidator('dev');

        $validator->validate($this->createApp(), $this->createManifestWithoutFeaturesRequiringSecret());

        $this->addToAssertionCount(1);
    }

    public function testThrowsForApplicableFeaturesWithoutAppSecretInDevEnvironment(): void
    {
        $validator = new AppFeatureValidator('dev');

        $this->expectExceptionObject(AppException::appSecretRequiredForFeatures('test', ['Admin Modules', 'Payment Methods', 'Tax providers', 'Webhooks']));

        $validator->validate($this->createApp(), $this->createManifestWithFeaturesRequiringSecret());
    }

    private function createApp(?string $secret = null): AppEntity
    {
        $app = new AppEntity();
        $app->setName('test');
        $app->setAppSecret($secret);

        return $app;
    }

    private function createManifestWithoutFeaturesRequiringSecret(): ManifestFixture
    {
        return ManifestFixture::empty();
    }

    private function createManifestWithFeaturesRequiringSecret(): ManifestFixture
    {
        return ManifestFixture::empty()
            ->withAdminModule()
            ->withPaymentMethod()
            ->withTaxProvider()
            ->withWebhook();
    }
}
