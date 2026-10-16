<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Authorization\Policy;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerLoginEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailSentEvent;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\BusinessEventRegistry;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Authorization\Policy\NotHookablePolicy;
use Shopware\Core\Framework\Webhook\Authorization\Subscription\Subscriber;
use Shopware\Core\Framework\Webhook\Hookable;
use Shopware\Core\Framework\Webhook\NotHookable;
use Shopware\Core\Framework\Webhook\Webhook;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NotHookablePolicy::class)]
class NotHookablePolicyTest extends TestCase
{
    public function testCoreEventsCarryingTheAttributeAreHandled(): void
    {
        $policy = $this->createPolicy();

        static::assertTrue($policy->handles(UserRecoveryRequestEvent::EVENT_NAME));
        static::assertTrue($policy->handles(CustomerAccountRecoverRequestEvent::EVENT_NAME));
        static::assertTrue($policy->handles(MailBeforeValidateEvent::EVENT_NAME));
        static::assertTrue($policy->handles(MailBeforeSentEvent::EVENT_NAME));
    }

    public function testEventsWithoutTheAttributeAreNotHandled(): void
    {
        $policy = $this->createPolicy();

        static::assertFalse($policy->handles(CustomerLoginEvent::EVENT_NAME));
        static::assertFalse($policy->handles(MailSentEvent::EVENT_NAME));
    }

    public function testClassesAddedByABundleAreHandled(): void
    {
        static::assertTrue($this->createPolicy([MarkedEvent::class])->handles('test.marked'));
    }

    public function testAnAppMayNotReceiveIt(): void
    {
        static::assertFalse($this->createPolicy()->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook()));
    }

    public function testAWebhookWithoutAnAppMayNotReceiveIt(): void
    {
        static::assertFalse($this->createPolicy()->permitsDelivery(static::createStub(Hookable::class), $this->createWebhook(appName: null)));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testAppSubscriptionIsPermittedBeforeTheNextMajor(): void
    {
        static::assertTrue($this->createPolicy()->permitsSubscription(UserRecoveryRequestEvent::EVENT_NAME, Subscriber::app(static::createStub(Manifest::class))));
    }

    public function testAppSubscriptionIsDeniedFromTheNextMajor(): void
    {
        static::assertFalse($this->createPolicy()->permitsSubscription(UserRecoveryRequestEvent::EVENT_NAME, Subscriber::app(static::createStub(Manifest::class))));
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testApiSubscriptionIsDeniedEvenBeforeTheNextMajor(): void
    {
        static::assertFalse($this->createPolicy()->permitsSubscription(UserRecoveryRequestEvent::EVENT_NAME, Subscriber::user()));
    }

    /**
     * @param list<class-string<FlowEventAware>> $addedClasses
     */
    private function createPolicy(array $addedClasses = []): NotHookablePolicy
    {
        $registry = new BusinessEventRegistry();
        if ($addedClasses !== []) {
            $registry->addClasses($addedClasses);
        }

        return new NotHookablePolicy($registry);
    }

    private function createWebhook(?string $appName = 'SwagApp'): Webhook
    {
        return new Webhook(
            id: 'webhook-id',
            webhookName: 'hook',
            eventName: 'user.recovery.request',
            url: 'https://example.com',
            onlyLiveVersion: false,
            appId: $appName === null ? null : 'app-id',
            appName: $appName,
            appSourceType: null,
            appActive: true,
            appVersion: null,
            appSecret: null,
            appAclRoleId: null,
        );
    }
}

/**
 * @internal
 */
#[Package('framework')]
#[NotHookable]
class MarkedEvent extends Event implements FlowEventAware
{
    public function getName(): string
    {
        return 'test.marked';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }
}
