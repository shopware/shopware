<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\MailTemplate\Defaults;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\MailTemplate\Defaults\MailTemplateDefaultsRegistry;
use Shopware\Core\Content\MailTemplate\MailTemplateLoader;

/**
 * @internal
 */
#[CoversClass(MailTemplateDefaultsRegistry::class)]
class MailTemplateDefaultsRegistryTest extends TestCase
{
    public function testLoadsCoreFixtures(): void
    {
        $registry = new MailTemplateDefaultsRegistry(__DIR__ . '/../_fixtures');

        static::assertTrue($registry->has('order_confirmation'));
        static::assertSame(['order_confirmation'], $registry->getTechnicalNames());
        static::assertSame(['en-GB', 'de-DE'], $registry->getLocales('order_confirmation'));
    }

    public function testReturnsNullForUnknownTechnicalName(): void
    {
        $registry = new MailTemplateDefaultsRegistry(__DIR__ . '/../_fixtures');

        static::assertNull($registry->getDefault('does_not_exist', 'en-GB'));
    }

    public function testFallsBackToEnGbForUnknownLocale(): void
    {
        $registry = new MailTemplateDefaultsRegistry(__DIR__ . '/../_fixtures');

        $default = $registry->getDefault('order_confirmation', 'fr-FR');

        static::assertNotNull($default);
        static::assertSame('en-GB', $default->locale);
        static::assertSame('Your order {{ order.orderNumber }}', $default->subject);
    }

    public function testReturnsRequestedLocaleWhenAvailable(): void
    {
        $registry = new MailTemplateDefaultsRegistry(__DIR__ . '/../_fixtures');

        $default = $registry->getDefault('order_confirmation', 'de-DE');

        static::assertNotNull($default);
        static::assertSame('Ihre Bestellung {{ order.orderNumber }}', $default->subject);
    }

    public function testRegisterAddsThirdPartyTemplates(): void
    {
        $registry = new MailTemplateDefaultsRegistry(__DIR__ . '/../_fixtures');

        $extra = MailTemplateLoader::load(__DIR__ . '/../_fixtures');

        // Re-registering the same set is allowed; later registrations overwrite earlier ones.
        $registry->register($extra);

        static::assertTrue($registry->has('order_confirmation'));
    }

    public function testRemoveDropsTemplates(): void
    {
        $registry = new MailTemplateDefaultsRegistry(__DIR__ . '/../_fixtures');

        $registry->remove(['order_confirmation']);

        static::assertFalse($registry->has('order_confirmation'));
        static::assertNull($registry->getDefault('order_confirmation', 'en-GB'));
    }
}
