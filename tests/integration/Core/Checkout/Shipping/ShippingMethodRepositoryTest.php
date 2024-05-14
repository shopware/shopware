<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Shipping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * @phpstan-type DeliveryTimeData array{id: string, name: string, min: int, max: int, unit: string}
 *
 * @internal
 */
#[Package('checkout')]
class ShippingMethodRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<ShippingMethodCollection>
     */
    private EntityRepository $shippingRepository;

    private string $shippingMethodId;

    private string $ruleId;

    protected function setUp(): void
    {
        $this->shippingRepository = $this->getContainer()->get('shipping_method.repository');
        $this->shippingMethodId = Uuid::randomHex();
        $this->ruleId = Uuid::randomHex();
    }

    public function testCreateShippingMethod(): void
    {
        $defaultContext = Context::createDefaultContext();

        $shippingMethod = $this->createShippingMethodDummyArray();

        $this->shippingRepository->create($shippingMethod, $defaultContext);

        $criteria = new Criteria([$this->shippingMethodId]);
        $criteria->addAssociation('availabilityRule');

        $resultSet = $this->shippingRepository->search($criteria, $defaultContext)->getEntities();

        $rule = $resultSet->first();

        static::assertNotNull($rule);
        static::assertNotNull($rule->getAvailabilityRule());

        static::assertSame($this->shippingMethodId, $rule->getId());
        static::assertSame($this->ruleId, $rule->getAvailabilityRule()->getId());
        static::assertSame($this->ruleId, $rule->getAvailabilityRuleId());
    }

    public function testCreateShippingMethodWithoutAvailabilityRule(): void
    {
        $defaultContext = Context::createDefaultContext();

        $shippingMethod = $this->createShippingMethodDummyArray();
        unset($shippingMethod[0]['availabilityRule']);

        $this->shippingRepository->create($shippingMethod, $defaultContext);

        $resultSet = $this->shippingRepository->search(new Criteria([$this->shippingMethodId]), $defaultContext)->getEntities()->first();

        static::assertNotNull($resultSet);
        static::assertNull($resultSet->getAvailabilityRuleId());
    }

    public function testUpdateShippingMethod(): void
    {
        $defaultContext = Context::createDefaultContext();

        $shippingMethod = $this->createShippingMethodDummyArray();

        $this->shippingRepository->create($shippingMethod, $defaultContext);

        $updateParameter = [
            'id' => $this->shippingMethodId,
            'availabilityRule' => [
                'id' => Uuid::randomHex(),
                'name' => 'test update',
                'priority' => 5,
                'created_at' => new \DateTime(),
            ],
        ];

        $this->shippingRepository->update([$updateParameter], $defaultContext);

        $criteria = new Criteria([$this->shippingMethodId]);
        $criteria->addAssociation('availabilityRule');

        $resultSet = $this->shippingRepository->search($criteria, $defaultContext)->getEntities();
        $rule = $resultSet->first();
        static::assertNotNull($rule);
        static::assertNotNull($rule->getAvailabilityRule());

        static::assertSame('test update', $rule->getAvailabilityRule()->getName());
    }

    public function testShippingMethodCanBeDeleted(): void
    {
        $defaultContext = Context::createDefaultContext();

        $shippingMethod = $this->createShippingMethodDummyArray();

        $this->shippingRepository->create($shippingMethod, $defaultContext);

        $primaryKey = [
            'id' => $this->shippingMethodId,
        ];

        $this->shippingRepository->delete([$primaryKey], $defaultContext);

        $criteria = new Criteria([$this->shippingMethodId]);

        $resultSet = $this->shippingRepository->search($criteria, $defaultContext);

        static::assertCount(0, $resultSet);
    }

    public function testThrowsExceptionIfNotAllRequiredValuesAreGiven(): void
    {
        $defaultContext = Context::createDefaultContext();
        $shippingMethod = $this->createShippingMethodDummyArray();

        unset($shippingMethod[0]['name']);

        try {
            $this->shippingRepository->create($shippingMethod, $defaultContext);

            static::fail('The type should always be required!');
        } catch (WriteException $e) {
            $constraintViolation = $e->getExceptions()[0];
            static::assertInstanceOf(WriteConstraintViolationException::class, $constraintViolation);
            static::assertEquals('/name', $constraintViolation->getViolations()->get(0)->getPropertyPath());
        }
    }

    public function testSearchWithoutEntriesWillBeEmpty(): void
    {
        $defaultContext = Context::createDefaultContext();

        $result = $this->shippingRepository->search(new Criteria([$this->shippingMethodId]), $defaultContext);

        static::assertEmpty($result);
    }

    /**
     * @return array<array{id: string, bindShippingfree: bool, name: string, tax_type: null, availabilityRule: array<string, mixed>, deliveryTime: DeliveryTimeData}>
     */
    private function createShippingMethodDummyArray(): array
    {
        return [
            [
                'id' => $this->shippingMethodId,
                'bindShippingfree' => false,
                'name' => 'test',
                'technicalName' => 'shipping_test',
                'tax_type' => null,
                'availabilityRule' => [
                    'id' => $this->ruleId,
                    'name' => 'asd',
                    'priority' => 2,
                ],
                'deliveryTime' => $this->createDeliveryTimeData(),
            ],
        ];
    }

    /**
     * @return DeliveryTimeData
     */
    private function createDeliveryTimeData(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => 'testDeliveryTime',
            'min' => 1,
            'max' => 90,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ];
    }
}
