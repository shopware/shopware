<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\MailTemplate\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Customer\Event\CustomerAccountRecoverRequestEvent;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Content\MailTemplate\Service\MailDataProvider;
use Shopware\Core\Content\Newsletter\Event\NewsletterRegisterEvent;
use Shopware\Core\Content\Product\SalesChannel\Review\Event\ReviewFormEvent;
use Shopware\Core\Framework\Context;
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
                $this->mailDataProvider->getTemplateData($flowEventClass, Context::createDefaultContext(), 42),
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
}
