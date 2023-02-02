<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\FlowEventAware;
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
use Shopware\Core\System\Tax\TaxCollection;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @internal
 */
class BusinessEventEncoderTest extends TestCase
{
    use IntegrationTestBehaviour;

    private BusinessEventEncoder $businessEventEncoder;

    public function setUp(): void
    {
        $this->businessEventEncoder = $this->getContainer()->get(BusinessEventEncoder::class);
    }

    /**
     * @dataProvider getEvents
     */
    public function testScalarEvents(FlowEventAware $event): void
    {
        $shopwareVersion = $this->getContainer()->getParameter('kernel.shopware_version');
        static::assertTrue(
            method_exists($event, 'getEncodeValues'),
            'Event does not have method getEncodeValues'
        );
        static::assertEquals($event->getEncodeValues($shopwareVersion), $this->businessEventEncoder->encode($event));
    }

    /**
     * @return array<int, mixed>
     */
    public function getEvents(): array
    {
        return [
            [new ScalarBusinessEvent()],
            [new StructuredObjectBusinessEvent()],
            [new StructuredArrayObjectBusinessEvent()],
            [new UnstructuredObjectBusinessEvent()],
            [new EntityBusinessEvent($this->getTaxEntity())],
            [new CollectionBusinessEvent($this->getTaxCollection())],
            [new ArrayBusinessEvent($this->getTaxCollection())],
            [new NestedEntityBusinessEvent($this->getTaxEntity())],
        ];
    }

    public function testInvalidType(): void
    {
        static::expectException(\RuntimeException::class);
        $this->businessEventEncoder->encode(new InvalidTypeBusinessEvent());
    }

    public function testInvalidAvailableData(): void
    {
        static::expectException(\RuntimeException::class);
        $this->businessEventEncoder->encode(new InvalidAvailableDataBusinessEvent());
    }

    private function getTaxEntity(): TaxEntity
    {
        /** @var EntityRepository $taxRepo */
        $taxRepo = $this->getContainer()->get('tax.repository');

        return $taxRepo->search(new Criteria(), Context::createDefaultContext())->first();
    }

    private function getTaxCollection(): TaxCollection
    {
        /** @var EntityRepository $taxRepo */
        $taxRepo = $this->getContainer()->get('tax.repository');

        $taxes = $taxRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();
        static::assertInstanceOf(TaxCollection::class, $taxes);

        return $taxes;
    }
}
