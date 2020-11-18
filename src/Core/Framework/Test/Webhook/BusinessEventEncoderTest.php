<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\BusinessEventInterface;
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

class BusinessEventEncoderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var BusinessEventEncoder
     */
    private $businessEventEncoder;

    public function setUp(): void
    {
        $this->businessEventEncoder = $this->getContainer()->get(BusinessEventEncoder::class);
    }

    /**
     * @dataProvider getEvents
     */
    public function testScalarEvents(BusinessEventInterface $event): void
    {
        $shopwareVersion = $this->getContainer()->getParameter('kernel.shopware_version');
        static::assertEquals($event->getEncodeValues($shopwareVersion), $this->businessEventEncoder->encode($event));
    }

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
        /** @var EntityRepositoryInterface $taxRepo */
        $taxRepo = $this->getContainer()->get('tax.repository');

        return $taxRepo->search(new Criteria(), Context::createDefaultContext())->first();
    }

    private function getTaxCollection(): TaxCollection
    {
        /** @var EntityRepositoryInterface $taxRepo */
        $taxRepo = $this->getContainer()->get('tax.repository');

        return $taxRepo->search(new Criteria(), Context::createDefaultContext())->getEntities();
    }
}
