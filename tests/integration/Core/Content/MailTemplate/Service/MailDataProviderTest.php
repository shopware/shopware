<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\CustomerGroupProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\User\Recovery\UserRecoveryRequestEvent;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Integration\Traits\SnapshotTesting;

/**
 * @internal
 */
#[Package('after-sales')]
class MailDataProviderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use SnapshotTesting;

    private readonly MailDataProvider $mailDataProvider;

    protected function setUp(): void
    {
        $this->clearRemnantCustomFields();

        $this->mailDataProvider = $this->getContainer()->get(MailDataProvider::class);
    }

    /**
     * @param class-string<FlowEventAware> $flowEventClass
     */
    #[DataProvider('flowTriggerEvents')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEventTemplateDataSnapshot(string $flowEventClass, string $fileName): void
    {
        $templateData = \json_decode(
            \json_encode(
                $this->mailDataProvider->getTemplateData(Context::createDefaultContext(), $flowEventClass, [], [], 42),
                \JSON_PRETTY_PRINT | \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION
            ),
            true
        );

        $this->assertSnapshot($fileName, [
            [
                'type' => self::TYPE_JSON,
                'actual' => $templateData,
            ],
        ]);
    }

    public static function flowTriggerEvents(): \Generator
    {
        yield 'checkoutOrderPlaced event' => [
            'flowEventClass' => CheckoutOrderPlacedEvent::class,
            'fileName' => 'checkout_order_placed_event_data',
        ];

        yield 'customerAccountRecoverRequest event' => [
            'flowEventClass' => CustomerAccountRecoverRequestEvent::class,
            'fileName' => 'customer_account_recover_request_event_data',
        ];

        yield 'newsletterRegister event' => [
            'flowEventClass' => NewsletterRegisterEvent::class,
            'fileName' => 'newsletter_register_event_data',
        ];

        yield 'orderPaymentMethodChanged event' => [
            'flowEventClass' => OrderPaymentMethodChangedEvent::class,
            'fileName' => 'order_payment_method_changed_event_data',
        ];

        yield 'reviewForm event' => [
            'flowEventClass' => ReviewFormEvent::class,
            'fileName' => 'review_form_event_data',
        ];

        yield 'userRecoveryRequest event' => [
            'flowEventClass' => UserRecoveryRequestEvent::class,
            'fileName' => 'user_recovery_request_event_data',
        ];
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEventTemplateDataWithProvidedEntities(): void
    {
        $customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $customerGroupProvider = $this->getContainer()->get(CustomerGroupProvider::class);
        \assert($customerGroupProvider instanceof CustomerGroupProvider);

        $context = Context::createDefaultContext();

        $customerGroupId = $customerGroupRepository->search(new Criteria(), $context)->first()?->getUniqueIdentifier();
        \assert(\is_string($customerGroupId));

        $customerGroupEntity = $customerGroupProvider->getData($customerGroupId, $context);
        \assert($customerGroupEntity instanceof CustomerGroupEntity);

        $templateData = $this->mailDataProvider->getTemplateData(
            Context::createDefaultContext(),
            CheckoutOrderPlacedEvent::class,    // provides a customer group itself, so the generated entity should be replaced by the entity in the database
            ['customer_group' => $customerGroupEntity->getId()],
            [],
            42
        );

        static::assertSame($customerGroupEntity->getId(), $templateData['customerGroupId']);
        static::assertEquals($customerGroupEntity, $templateData['customerGroup']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEventTemplateDataWithProvidedEntitiesAndInjectedTemplateData(): void
    {
        $customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $customerGroupProvider = $this->getContainer()->get(CustomerGroupProvider::class);
        \assert($customerGroupProvider instanceof CustomerGroupProvider);

        $context = Context::createDefaultContext();

        $customerGroupId = $customerGroupRepository->search(new Criteria(), $context)->first()?->getUniqueIdentifier();
        \assert(\is_string($customerGroupId));

        $customerGroupEntity = $customerGroupProvider->getData($customerGroupId, $context);
        \assert($customerGroupEntity instanceof CustomerGroupEntity);

        $templateData = $this->mailDataProvider->getTemplateData(
            Context::createDefaultContext(),
            CheckoutOrderPlacedEvent::class,    // provides a customer group itself, so the generated entity should be replaced by the entity in the database
            ['customer_group' => $customerGroupEntity->getId()],
            ['customer_group_injected' => 'foobar'],
            42
        );

        static::assertSame($customerGroupEntity->getId(), $templateData['customerGroupId']);
        static::assertEquals($customerGroupEntity, $templateData['customerGroup']);
        static::assertEquals('foobar', $templateData['customer_group_injected']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testEventTemplateDataWithProvidedEntitiesAndInjectedTemplateDataOverwrite(): void
    {
        $customerGroupRepository = $this->getContainer()->get('customer_group.repository');
        $customerGroupProvider = $this->getContainer()->get(CustomerGroupProvider::class);
        \assert($customerGroupProvider instanceof CustomerGroupProvider);

        $context = Context::createDefaultContext();

        $customerGroupId = $customerGroupRepository->search(new Criteria(), $context)->first()?->getUniqueIdentifier();
        \assert(\is_string($customerGroupId));

        $customerGroupEntity = $customerGroupProvider->getData($customerGroupId, $context);
        \assert($customerGroupEntity instanceof CustomerGroupEntity);

        $templateData = $this->mailDataProvider->getTemplateData(
            Context::createDefaultContext(),
            CheckoutOrderPlacedEvent::class,    // provides a customer group itself, so the generated entity should be replaced by the entity in the database
            ['customer_group' => $customerGroupEntity->getId()],
            ['customerGroup' => 'foobar'],
            42
        );

        static::assertSame($customerGroupEntity->getId(), $templateData['customerGroupId']);
        static::assertEquals('foobar', $templateData['customerGroup']);
    }

    private function clearRemnantCustomFields(): void
    {
        $definitionInstanceRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        \assert($definitionInstanceRegistry instanceof DefinitionInstanceRegistry);
        $definitions = $definitionInstanceRegistry->getDefinitions();

        foreach ($definitions as $definition) {
            $customFields = $definition->getFields()->get('customFields');

            if (!$customFields instanceof CustomFields) {
                continue;
            }

            $customFields->setPropertyMapping([]);
        }
    }
}
