<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Plugin;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationCollection;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Tests\Unit\Core\Framework\Plugin\_fixtures\ExampleBundle\ExampleBundle;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PluginEntity::class)]
class PluginEntityTest extends TestCase
{
    public function testScalarAccessorsRoundTrip(): void
    {
        $plugin = new PluginEntity();

        $plugin->setBaseClass(ExampleBundle::class);
        $plugin->setName('name');
        $plugin->setComposerName('composer-name');
        $plugin->setActive(true);
        $plugin->setManagedByComposer(true);
        $plugin->setPath('path');
        $plugin->setAuthor('author');
        $plugin->setCopyright('copyright');
        $plugin->setLicense('license');
        $plugin->setVersion('version');
        $plugin->setUpgradeVersion('upgrade-version');
        $plugin->setIconRaw('icon-raw');
        $plugin->setIcon('icon');
        $plugin->setLabel('label');
        $plugin->setDescription('description');
        $plugin->setManufacturerLink('manufacturer-link');
        $plugin->setSupportLink('support-link');
        $plugin->setAutoload(['psr-4' => ['Swag\\' => 'src/']]);

        static::assertSame(ExampleBundle::class, $plugin->getBaseClass());
        static::assertSame('name', $plugin->getName());
        static::assertSame('composer-name', $plugin->getComposerName());
        static::assertTrue($plugin->getActive());
        static::assertTrue($plugin->getManagedByComposer());
        static::assertSame('path', $plugin->getPath());
        static::assertSame('author', $plugin->getAuthor());
        static::assertSame('copyright', $plugin->getCopyright());
        static::assertSame('license', $plugin->getLicense());
        static::assertSame('version', $plugin->getVersion());
        static::assertSame('upgrade-version', $plugin->getUpgradeVersion());
        static::assertSame('icon-raw', $plugin->getIconRaw());
        static::assertSame('icon', $plugin->getIcon());
        static::assertSame('label', $plugin->getLabel());
        static::assertSame('description', $plugin->getDescription());
        static::assertSame('manufacturer-link', $plugin->getManufacturerLink());
        static::assertSame('support-link', $plugin->getSupportLink());
        static::assertSame(['psr-4' => ['Swag\\' => 'src/']], $plugin->getAutoload());
    }

    public function testAssociationAccessorsRoundTrip(): void
    {
        $plugin = new PluginEntity();

        $installedAt = new \DateTimeImmutable('2026-01-01 00:00:00');
        $upgradedAt = new \DateTimeImmutable('2026-01-01 00:00:00');
        $translations = new PluginTranslationCollection();
        $paymentMethods = new PaymentMethodCollection();

        $plugin->setInstalledAt($installedAt);
        $plugin->setUpgradedAt($upgradedAt);
        $plugin->setTranslations($translations);
        $plugin->setPaymentMethods($paymentMethods);

        static::assertSame($installedAt, $plugin->getInstalledAt());
        static::assertSame($upgradedAt, $plugin->getUpgradedAt());
        static::assertSame($translations, $plugin->getTranslations());
        static::assertSame($paymentMethods, $plugin->getPaymentMethods());
    }
}
