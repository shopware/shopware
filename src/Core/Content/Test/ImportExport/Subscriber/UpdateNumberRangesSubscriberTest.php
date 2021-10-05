<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\ImportExport\Subscriber;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Shopware\Core\Content\ImportExport\Event\Subscriber\UpdateNumberRangesSubscriber;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeState\NumberRangeStateEntity;
use Shopware\Core\System\NumberRange\NumberRangeEntity;

class UpdateNumberRangesSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepositoryInterface $numberRangeRepository;

    private UpdateNumberRangesSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->numberRangeRepository = $this->getContainer()->get('number_range.repository');
        $this->subscriber = $this->getContainer()->get(UpdateNumberRangesSubscriber::class);
    }

    /**
     * @dataProvider numberPatternProvider
     */
    public function testNumberRangeIsUpdated(string $pattern, string $number, int $startNumber, bool $shouldMatch = true): void
    {
        $context = Context::createDefaultContext();
        $criteria = new Criteria();
        $criteria->addAssociation('state');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            new EqualsFilter('type.technicalName', ProductDefinition::ENTITY_NAME),
            new EqualsFilter('global', true),
        ]));

        /** @var NumberRangeEntity $numberRangeEntity */
        $numberRangeEntity = $this->numberRangeRepository->search($criteria, $context)->first();
        $state = $numberRangeEntity->getState();

        if (!$state) {
            $state = new NumberRangeStateEntity();
            $state->setId(Uuid::randomHex());
            $state->setLastValue(0);
        }

        static::assertNotEquals($startNumber, $state->getLastValue());

        $this->numberRangeRepository->update([
            [
                'id' => $numberRangeEntity->getId(),
                'pattern' => $pattern,
                'state' => [
                    'id' => $state->getId(),
                    'lastValue' => $state->getLastValue(),
                ],
            ],
        ], $context);

        $entityWrittenEvent = new EntityWrittenEvent(
            ProductDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    Uuid::randomHex(),
                    ['productNumber' => $number],
                    ProductDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_INSERT
                ),
            ],
            $context
        );
        $event = new ImportExportAfterImportRecordEvent(
            new EntityWrittenContainerEvent($context, new NestedEventCollection([$entityWrittenEvent]), []),
            [],
            [],
            new Config([], ['sourceEntity' => ProductDefinition::ENTITY_NAME]),
            $context
        );

        $this->subscriber->onAfterImportRecord($event);

        /** @var NumberRangeEntity $numberRangeEntity */
        $numberRangeEntity = $this->numberRangeRepository->search($criteria, $context)->first();

        if ($shouldMatch) {
            static::assertEquals($startNumber, $numberRangeEntity->getState()->getLastValue());
        } else {
            static::assertNotEquals($startNumber, $numberRangeEntity->getState()->getLastValue());
        }
    }

    public function numberPatternProvider(): array
    {
        return [
            ['prefix_{n}_{date_dmY}_{date}_su5ffix', 'prefix_10232_22032020_2020-03-22_su5ffix', 10232],
            ['prefix_{n}{date_dmY}_{date}_su5ffix', 'prefix_1023222032020_2020-03-22_su5ffix', 10232],
            ['prefix_{n}{date_dmY}_{date}', 'prefix_1023222032020_2020-03-22', 10232],
            ['prefix_{n}{date_dmY}', 'prefix_1023222032020', 10232],
            ['prefix_{n}', 'prefix_10232', 10232],
            ['{date}{n}{date_dmY}', '2020-03-221023222032020', 10232],
            ['{date}{date_dmY}{n}', '2020-03-222203202010232', 10232],
            ['{n}-suffix', '10232-suffix', 10232],
            ['{n}{date}{date_dmY}', '102322020-03-2222032020', 10232],
            ['{n}', '10232', 10232],
            ['{n}{date_j}', '1023211', 10232, false],
            ['{n}', 'custom_number_10232', 10232, false],
        ];
    }
}
