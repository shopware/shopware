<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Consent\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Shopware\Core\System\Consent\Event\ConsentHookableEventDescriber;
use Shopware\Core\System\Consent\Service\ConsentService;
use Shopware\Tests\Unit\Core\System\Consent\TestDefinition;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(ConsentHookableEventDescriber::class)]
class ConsentHookableEventDescriberTest extends TestCase
{
    public function testResolveReturnsConsentWebhookPrivileges(): void
    {
        $consentService = static::createStub(ConsentService::class);
        $consentService->method('definitions')->willReturn([
            'backend_data' => new TestDefinition('backend_data', 'system'),
            'product_analytics' => new TestDefinition('product_analytics', 'admin_user'),
        ]);

        $resolver = new ConsentHookableEventDescriber($consentService);

        static::assertEquals([
            new HookableEventDescription('consent.backend_data.accepted', 'Fires when the backend_data consent is accepted.', ['consent:backend_data:read']),
            new HookableEventDescription('consent.backend_data.revoked', 'Fires when the backend_data consent is revoked.', ['consent:backend_data:read']),
            new HookableEventDescription('consent.product_analytics.accepted', 'Fires when the product_analytics consent is accepted.', ['consent:product_analytics:read']),
            new HookableEventDescription('consent.product_analytics.revoked', 'Fires when the product_analytics consent is revoked.', ['consent:product_analytics:read']),
        ], $resolver->describe());
    }
}
