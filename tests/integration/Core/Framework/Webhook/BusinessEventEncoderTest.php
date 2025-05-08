<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Webhook;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\ArrayBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\CollectionBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\EntityBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\InvalidAvailableDataBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\InvalidTypeBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\NestedEntityBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\ScalarBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\StructuredArrayObjectBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\StructuredObjectBusinessEvent;
use Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\UnstructuredObjectBusinessEvent;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\Framework\Webhook\WebhookException;
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class BusinessEventEncoderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private BusinessEventEncoder $businessEventEncoder;

    protected function setUp(): void
    {
        $this->businessEventEncoder = static::getContainer()->get(BusinessEventEncoder::class);
    }

    #[DataProvider('getEvents')]
    public function testScalarEvents(FlowEventAware $event): void
    {
        $shopwareVersion = static::getContainer()->getParameter('kernel.shopware_version');
        static::assertTrue(
            method_exists($event, 'getEncodeValues'),
            'Event does not have method getEncodeValues'
        );
        static::assertEquals($event->getEncodeValues($shopwareVersion), $this->businessEventEncoder->encode($event));
    }

    public static function getEvents(): \Generator
    {
        $tax = new TaxEntity();
        $tax->setId('tax-id');
        $tax->setName('test');
        $tax->setTaxRate(19);
        $tax->setPosition(1);

        yield 'ScalarBusinessEvent' => [new ScalarBusinessEvent()];
        yield 'StructuredObjectBusinessEvent' => [new StructuredObjectBusinessEvent()];
        yield 'StructuredArrayObjectBusinessEvent' => [new StructuredArrayObjectBusinessEvent()];
        yield 'UnstructuredObjectBusinessEvent' => [new UnstructuredObjectBusinessEvent()];
        yield 'EntityBusinessEvent' => [new EntityBusinessEvent($tax)];
        yield 'CollectionBusinessEvent' => [new CollectionBusinessEvent(new TaxCollection([$tax]))];
        yield 'ArrayBusinessEvent' => [new ArrayBusinessEvent(new TaxCollection([$tax]))];
        yield 'NestedEntityBusinessEvent' => [new NestedEntityBusinessEvent($tax)];
    }

    public function testInvalidType(): void
    {
        static::expectException(\RuntimeException::class);
        $this->businessEventEncoder->encode(new InvalidTypeBusinessEvent());
    }

    public function testInvalidAvailableData(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            static::expectException(\RuntimeException::class);
            $this->businessEventEncoder->encode(new InvalidAvailableDataBusinessEvent());

            return;
        }

        try {
            $this->businessEventEncoder->encode(new InvalidAvailableDataBusinessEvent());
        } catch (WebhookException $exception) {
            static::assertSame('Invalid available DataMapping, could not get property "invalid" on instance of Shopware\Core\Framework\Test\Webhook\_fixtures\BusinessEvents\InvalidAvailableDataBusinessEvent', $exception->getMessage());
            static::assertSame(WebhookException::INVALID_DATA_MAPPING, $exception->getErrorCode());

            return;
        }

        static::fail('Exception should have been thrown');
    }

    public function testEncodeWithInvalidObjectOrData(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            static::expectException(\RuntimeException::class);
            $this->businessEventEncoder->encode(new InvalidAvailableDataBusinessEvent());

            return;
        }

        try {
            $this->businessEventEncoder->encode(new InvalidTypeBusinessEvent());
        } catch (WebhookException $exception) {
            static::assertSame('Unknown EventDataType: invalid', $exception->getMessage());
            static::assertSame(WebhookException::UNKNOWN_DATA_TYPE, $exception->getErrorCode());

            return;
        }

        static::fail('Exception should have been thrown');
    }
}
