<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Validation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\Framework\Webhook\Validation\WebhookTargetValidator;
use Shopware\Core\Framework\Webhook\Validation\WebhookUrlWriteValidator;
use Shopware\Core\Framework\Webhook\WebhookDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(WebhookUrlWriteValidator::class)]
class WebhookUrlWriteValidatorTest extends TestCase
{
    private WriteContext $context;

    private WebhookDefinition $webhookDefinition;

    private SalesChannelDefinition $salesChannelDefinition;

    protected function setUp(): void
    {
        $this->context = WriteContext::createFromContext(Context::createDefaultContext());

        $registry = new StaticDefinitionInstanceRegistry(
            [WebhookDefinition::class, AppDefinition::class, SalesChannelDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $webhookDefinition = $registry->get(WebhookDefinition::class);
        static::assertInstanceOf(WebhookDefinition::class, $webhookDefinition);
        $this->webhookDefinition = $webhookDefinition;

        $salesChannelDefinition = $registry->get(SalesChannelDefinition::class);
        static::assertInstanceOf(SalesChannelDefinition::class, $salesChannelDefinition);
        $this->salesChannelDefinition = $salesChannelDefinition;
    }

    public function testSubscribedEvents(): void
    {
        $events = WebhookUrlWriteValidator::getSubscribedEvents();

        static::assertSame(['preValidate'], array_values($events));
        static::assertArrayHasKey(PreWriteValidationEvent::class, $events);
    }

    public function testIgnoresOtherEntities(): void
    {
        $command = new InsertCommand(
            $this->salesChannelDefinition,
            ['name' => 'channel'],
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0/'
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        $this->createValidator()->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testIgnoresWebhookUpdateWithoutUrl(): void
    {
        $command = new UpdateCommand(
            $this->webhookDefinition,
            ['active' => false],
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0/'
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        $this->createValidator()->preValidate($event);

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    public function testRejectsInvalidWebhookUrl(): void
    {
        $event = $this->dispatch('http://example.com/webhook');

        $thrown = null;
        try {
            $event->getExceptions()->tryToThrow();
        } catch (WriteException $e) {
            $thrown = $e;
        }

        static::assertNotNull($thrown);

        $violationException = $thrown->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $violationException);
        static::assertSame('/url', $violationException->getViolations()->get(0)->getPropertyPath());
    }

    public function testAcceptsValidWebhookUrl(): void
    {
        $event = $this->dispatch('https://example.com/webhook');

        static::assertCount(0, $event->getExceptions()->getExceptions());
    }

    private function dispatch(string $url): PreWriteValidationEvent
    {
        $command = new InsertCommand(
            $this->webhookDefinition,
            [
                'name' => 'webhook',
                'event_name' => 'checkout.order.placed',
                'url' => $url,
            ],
            ['id' => Uuid::randomBytes()],
            static::createStub(EntityExistence::class),
            '/0/'
        );

        $event = new PreWriteValidationEvent($this->context, [$command]);
        $this->createValidator()->preValidate($event);

        return $event;
    }

    private function createValidator(): WebhookUrlWriteValidator
    {
        return new WebhookUrlWriteValidator(new WebhookTargetValidator(false, [], static fn (string $host): array => [
            ['ip' => '93.184.216.34'],
        ]));
    }
}
