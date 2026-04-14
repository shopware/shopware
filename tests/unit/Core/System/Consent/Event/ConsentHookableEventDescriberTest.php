<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Shopware\Core\System\Consent\ConsentDefinitionRegistry;
use Shopware\Core\System\Consent\Event\ConsentHookableEventDescriber;
use Shopware\Tests\Unit\Core\System\Consent\TestDefinition;

/**
 * @internal
 */
#[CoversClass(ConsentHookableEventDescriber::class)]
class ConsentHookableEventDescriberTest extends TestCase
{
    private const MANIFEST_FIXTURE = __DIR__ . '/../../../../../integration/Core/Framework/App/Manifest/_fixtures/minimal/manifest.xml';

    public function testResolveReturnsConsentWebhookPrivileges(): void
    {
        $resolver = new ConsentHookableEventDescriber(new ConsentDefinitionRegistry([
            new TestDefinition('backend_data', 'system'),
            new TestDefinition('product_analytics', 'admin_user'),
        ]));

        static::assertEquals([
            new HookableEventDescription('consent.backend_data.accepted', ['consent:backend_data:read']),
            new HookableEventDescription('consent.backend_data.revoked', ['consent:backend_data:read']),
            new HookableEventDescription('consent.product_analytics.accepted', ['consent:product_analytics:read']),
            new HookableEventDescription('consent.product_analytics.revoked', ['consent:product_analytics:read']),
        ], $resolver->describe(Manifest::createFromXmlFile(self::MANIFEST_FIXTURE)));
    }
}
