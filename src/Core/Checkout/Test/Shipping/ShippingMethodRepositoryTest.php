<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Shipping;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ShippingMethodRepositoryTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingRepository;

    /**
     * @var string
     */
    private $shippingMethodId;

    /**
     * @var string
     */
    private $ruleId;

    public function setUp(): void
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
        $criteria->addAssociation('availabilityRules');

        /** @var ShippingMethodCollection $resultSet */
        $resultSet = $this->shippingRepository->search($criteria, $defaultContext);

        static::assertSame($this->shippingMethodId, $resultSet->first()->getId());
        static::assertSame($this->ruleId, $resultSet->first()->getAvailabilityRules()->first()->getId());
        static::assertSame([$this->ruleId], $resultSet->first()->getAvailabilityRuleIds());
    }

    public function testUpdateShippingMethod(): void
    {
        $defaultContext = Context::createDefaultContext();

        $shippingMethod = $this->createShippingMethodDummyArray();

        $this->shippingRepository->create($shippingMethod, $defaultContext);

        $updateParameter = [
            'id' => $this->shippingMethodId,
            'availabilityRules' => [
                [
                    'id' => Uuid::randomHex(),
                    'name' => 'test update',
                    'priority' => 5,
                    'created_at' => new \DateTime(),
                ],
            ],
        ];

        $this->shippingRepository->update([$updateParameter], $defaultContext);

        $criteria = new Criteria([$this->shippingMethodId]);
        $criteria->addAssociation('availabilityRules');

        /** @var ShippingMethodCollection $resultSet */
        $resultSet = $this->shippingRepository->search($criteria, $defaultContext);

        static::assertCount(2, $resultSet->first()->getAvailabilityRules());
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

        /** @var ShippingMethodCollection $resultSet */
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
        } catch (WriteStackException $e) {
            static::assertStringStartsWith('Mapping failed, got 1 failure(s). Array', $e->getMessage());
        }
    }

    public function testSearchWithoutEntriesWillBeEmpty(): void
    {
        $defaultContext = Context::createDefaultContext();

        $result = $this->shippingRepository->search(new Criteria([$this->shippingMethodId]), $defaultContext);

        static::assertEmpty($result);
    }

    private function createRuleDummyArray(): array
    {
        return [
            [
                'id' => $this->ruleId,
                'name' => 'asd',
                'priority' => 2,
            ],
        ];
    }

    private function createShippingMethodDummyArray(): array
    {
        return [
            [
                'id' => $this->shippingMethodId,
                'bindShippingfree' => false,
                'name' => 'test',
                'availabilityRules' => $this->createRuleDummyArray(),
            ],
        ];
    }
}
