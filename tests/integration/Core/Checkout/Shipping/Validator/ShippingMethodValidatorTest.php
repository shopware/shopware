<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Shipping\Validator;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceCollection;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Checkout\Shipping\Validator\ShippingMethodValidator;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Sync\SyncBehavior;
use Shopware\Core\Framework\Api\Sync\SyncOperation;
use Shopware\Core\Framework\Api\Sync\SyncService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * @internal
 */
#[Package('checkout')]
class ShippingMethodValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private IdsCollection $ids;

    private Context $context;

    /**
     * @var EntityRepository<ShippingMethodCollection>
     */
    private EntityRepository $shippingMethodRepository;

    /**
     * @var EntityRepository<ShippingMethodPriceCollection>
     */
    private EntityRepository $shippingMethodPriceRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->context = Context::createDefaultContext();
        $this->shippingMethodRepository = static::getContainer()->get('shipping_method.repository');
        $this->shippingMethodPriceRepository = static::getContainer()->get('shipping_method_price.repository');
    }

    public function testDeletingTheLastPriceOfAnActiveShippingMethodIsRejected(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);

        $exception = $this->deletePrices(['price']);

        static::assertNotNull($exception);

        $violation = $this->firstViolation($exception);
        static::assertSame(ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE, $violation->getCode());
        static::assertSame('/prices', $violation->getPropertyPath());

        static::assertSame([$this->ids->get('price')], $this->fetchPriceIds());
    }

    public function testDeletingOneOfTwoPricesIsAllowed(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price', 'price2']);

        static::assertNull($this->deletePrices(['price']));

        static::assertSame([$this->ids->get('price2')], $this->fetchPriceIds());
    }

    public function testDeletingEveryPriceAtOnceIsRejected(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price', 'price2']);

        static::assertNotNull($this->deletePrices(['price', 'price2']));

        static::assertCount(2, $this->fetchPriceIds());
    }

    public function testDeletingTheLastPriceOfAnInactiveShippingMethodIsAllowed(): void
    {
        $this->createShippingMethod(active: false, priceKeys: ['price']);

        static::assertNull($this->deletePrices(['price']));

        static::assertSame([], $this->fetchPriceIds());
    }

    public function testDeletingTheShippingMethodItselfIsAllowed(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);

        $exception = $this->write(fn () => $this->shippingMethodRepository->delete(
            [['id' => $this->ids->get('shipping')]],
            $this->context
        ));

        static::assertNull($exception);
        static::assertSame([], $this->fetchPriceIds());
    }

    // The documented migration path: removal and replacement reach the validator as one write
    public function testReplacingEveryPriceWithinOneSyncIsAllowed(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);

        $operations = [
            new SyncOperation(
                'delete-old-prices',
                ShippingMethodPriceDefinition::ENTITY_NAME,
                SyncOperation::ACTION_DELETE,
                [['id' => $this->ids->get('price')]]
            ),
            new SyncOperation(
                'write-new-prices',
                ShippingMethodPriceDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                [$this->pricePayload('replacement') + ['shippingMethodId' => $this->ids->get('shipping')]]
            ),
        ];

        $exception = $this->write(fn () => static::getContainer()->get(SyncService::class)
            ->sync($operations, $this->context, new SyncBehavior()));

        static::assertNull($exception);
        static::assertSame([$this->ids->get('replacement')], $this->fetchPriceIds());
    }

    public function testPriceInsertedAndDeletedWithinOneSyncDoesNotOffsetTheLastPriceDeletion(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);

        $operations = [
            new SyncOperation(
                'write-temporary-price',
                ShippingMethodPriceDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                [$this->pricePayload('temporary') + ['shippingMethodId' => $this->ids->get('shipping')]]
            ),
            new SyncOperation(
                'delete-all-prices',
                ShippingMethodPriceDefinition::ENTITY_NAME,
                SyncOperation::ACTION_DELETE,
                [
                    ['id' => $this->ids->get('price')],
                    ['id' => $this->ids->get('temporary')],
                ]
            ),
        ];

        $exception = $this->write(fn () => static::getContainer()->get(SyncService::class)
            ->sync($operations, $this->context, new SyncBehavior()));

        static::assertNotNull($exception);
        static::assertSame(
            ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE,
            $this->firstViolation($exception)->getCode()
        );
        static::assertSame([$this->ids->get('price')], $this->fetchPriceIds());
    }

    public function testDeletingAndUpsertingTheSamePriceWithinOneSyncKeepsThePrice(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);

        $operations = [
            new SyncOperation(
                'delete-price',
                ShippingMethodPriceDefinition::ENTITY_NAME,
                SyncOperation::ACTION_DELETE,
                [['id' => $this->ids->get('price')]]
            ),
            new SyncOperation(
                'upsert-deleted-price',
                ShippingMethodPriceDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                [$this->pricePayload('price') + ['shippingMethodId' => $this->ids->get('shipping')]]
            ),
        ];

        $exception = $this->write(fn () => static::getContainer()->get(SyncService::class)
            ->sync($operations, $this->context, new SyncBehavior()));

        static::assertNull($exception);
        static::assertSame([$this->ids->get('price')], $this->fetchPriceIds());
    }

    public function testReplacingTheLastPriceInTwoSeparateWritesIsAllowed(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);

        $exception = $this->write(fn () => $this->shippingMethodPriceRepository->upsert(
            [$this->pricePayload('replacement') + ['shippingMethodId' => $this->ids->get('shipping')]],
            $this->context
        ));
        static::assertNull($exception);

        static::assertNull($this->deletePrices(['price']));
        static::assertSame([$this->ids->get('replacement')], $this->fetchPriceIds());
    }

    public function testRemovingEveryPriceWithinOneSyncIsRejected(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);

        $operations = [
            new SyncOperation(
                'rename',
                ShippingMethodDefinition::ENTITY_NAME,
                SyncOperation::ACTION_UPSERT,
                [['id' => $this->ids->get('shipping'), 'name' => 'renamed']]
            ),
            new SyncOperation(
                'delete-old-prices',
                ShippingMethodPriceDefinition::ENTITY_NAME,
                SyncOperation::ACTION_DELETE,
                [['id' => $this->ids->get('price')]]
            ),
        ];

        $exception = $this->write(fn () => static::getContainer()->get(SyncService::class)
            ->sync($operations, $this->context, new SyncBehavior()));

        static::assertNotNull($exception);
        static::assertSame(
            ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE,
            $this->firstViolation($exception)->getCode()
        );
        static::assertSame([$this->ids->get('price')], $this->fetchPriceIds());
    }

    public function testMovingTheLastPriceAwayFromAnActiveShippingMethodIsRejected(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);
        $this->createShippingMethod(active: true, priceKeys: [], key: 'target');

        $exception = $this->write(fn () => $this->shippingMethodPriceRepository->update([[
            'id' => $this->ids->get('price'),
            'shippingMethodId' => $this->ids->get('target'),
        ]], $this->context));

        static::assertNotNull($exception);
        static::assertSame(
            ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE,
            $this->firstViolation($exception)->getCode()
        );
        static::assertSame([$this->ids->get('price')], $this->fetchPriceIds());
        static::assertSame([], $this->fetchPriceIds('target'));
    }

    public function testActivatingAShippingMethodWithoutPricesIsRejected(): void
    {
        $this->createShippingMethod(active: false, priceKeys: []);

        $exception = $this->write(fn () => $this->shippingMethodRepository->update([[
            'id' => $this->ids->get('shipping'),
            'active' => true,
        ]], $this->context));

        static::assertNotNull($exception);
        static::assertSame(
            ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE,
            $this->firstViolation($exception)->getCode()
        );
    }

    public function testActivatingAShippingMethodTogetherWithItsFirstPriceIsAllowed(): void
    {
        $this->createShippingMethod(active: false, priceKeys: []);

        static::assertNull($this->write(fn () => $this->shippingMethodRepository->update([[
            'id' => $this->ids->get('shipping'),
            'active' => true,
            'prices' => [$this->pricePayload('price')],
        ]], $this->context)));
    }

    public function testDeactivatingAShippingMethodWithoutPricesIsAllowed(): void
    {
        $this->createShippingMethod(active: false, priceKeys: []);

        static::assertNull($this->write(fn () => $this->shippingMethodRepository->update([[
            'id' => $this->ids->get('shipping'),
            'active' => false,
        ]], $this->context)));
    }

    // Two-step creation is an established flow; the route keeps such a method out of the storefront
    public function testCreatingAnActiveShippingMethodWithoutPricesIsStillAllowed(): void
    {
        static::assertNull($this->write(fn () => $this->createShippingMethod(active: true, priceKeys: [])));
    }

    public function testDeletingTheLastResolvingPriceIsRejectedWhileAPriceWithoutCurrencyValuesRemains(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);
        $this->addPriceWithoutCurrencyValues('empty');

        $exception = $this->deletePrices(['price']);

        static::assertNotNull($exception);
        static::assertSame(
            ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE,
            $this->firstViolation($exception)->getCode()
        );
        static::assertCount(2, $this->fetchPriceIds());
    }

    public function testDeletingAPriceWithoutCurrencyValuesIsAllowedWhileAResolvingPriceRemains(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);
        $this->addPriceWithoutCurrencyValues('empty');

        static::assertNull($this->deletePrices(['empty']));

        static::assertSame([$this->ids->get('price')], $this->fetchPriceIds());
    }

    public function testClearingTheCurrencyValuesOfTheLastPriceIsRejected(): void
    {
        $this->createShippingMethod(active: true, priceKeys: ['price']);

        $exception = $this->write(fn () => $this->shippingMethodPriceRepository->update([[
            'id' => $this->ids->get('price'),
            'currencyPrice' => null,
        ]], $this->context));

        static::assertNotNull($exception);
        static::assertSame(
            ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE,
            $this->firstViolation($exception)->getCode()
        );
    }

    public function testActivatingAShippingMethodWhoseOnlyPriceHasNoCurrencyValuesIsRejected(): void
    {
        $this->createShippingMethod(active: false, priceKeys: []);
        $this->addPriceWithoutCurrencyValues('empty');

        $exception = $this->write(fn () => $this->shippingMethodRepository->update([[
            'id' => $this->ids->get('shipping'),
            'active' => true,
        ]], $this->context));

        static::assertNotNull($exception);
        static::assertSame(
            ShippingMethodValidator::VIOLATION_ACTIVE_WITHOUT_PRICE,
            $this->firstViolation($exception)->getCode()
        );
    }

    private function addPriceWithoutCurrencyValues(string $key, string $shippingMethodKey = 'shipping'): void
    {
        $this->shippingMethodPriceRepository->create([[
            'id' => $this->ids->create($key),
            'shippingMethodId' => $this->ids->get($shippingMethodKey),
            'calculation' => 1,
            'quantityStart' => 2,
        ]], $this->context);
    }

    /**
     * @param list<string> $priceKeys
     */
    private function createShippingMethod(bool $active, array $priceKeys, string $key = 'shipping'): void
    {
        $this->shippingMethodRepository->create([[
            'id' => $this->ids->create($key),
            'active' => $active,
            'name' => 'issue-19001-' . $key,
            'technicalName' => 'shipping_issue_19001_' . $key,
            'deliveryTime' => [
                'id' => Uuid::randomHex(),
                'name' => 'testDeliveryTime',
                'min' => 1,
                'max' => 3,
                'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
            ],
            'prices' => array_map($this->pricePayload(...), $priceKeys),
        ]], $this->context);
    }

    /**
     * @return array<string, mixed>
     */
    private function pricePayload(string $key): array
    {
        return [
            'id' => $this->ids->create($key),
            'calculation' => 1,
            'quantityStart' => 1,
            'currencyPrice' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'net' => 10,
                    'gross' => 11,
                    'linked' => false,
                ],
            ],
        ];
    }

    /**
     * @param list<string> $priceKeys
     */
    private function deletePrices(array $priceKeys): ?WriteException
    {
        return $this->write(fn () => $this->shippingMethodPriceRepository->delete(
            array_map(fn (string $key) => ['id' => $this->ids->get($key)], $priceKeys),
            $this->context
        ));
    }

    private function write(callable $write): ?WriteException
    {
        try {
            $write();
        } catch (WriteException $exception) {
            return $exception;
        }

        return null;
    }

    private function firstViolation(WriteException $exception): ConstraintViolationInterface
    {
        $inner = $exception->getExceptions()[0];
        static::assertInstanceOf(WriteConstraintViolationException::class, $inner);

        return $inner->getViolations()->get(0);
    }

    /**
     * @return list<string>
     */
    private function fetchPriceIds(string $shippingMethodKey = 'shipping'): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('shippingMethodId', $this->ids->get($shippingMethodKey)));

        /** @var list<string> $ids */
        $ids = $this->shippingMethodPriceRepository->searchIds($criteria, $this->context)->getIds();
        sort($ids);

        return $ids;
    }
}
