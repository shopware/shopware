<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\DeliveryTime\DeliveryTimeEntity;

/**
 * @internal
 */
#[Package('services-settings')]
class ShippingMethodRuleAccessibleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    /**
     * @var array<array<string, mixed>>
     */
    private array $rule = [];

    private string $ruleId;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');

        $this->prepareSimpleTestData();
    }

    public function testIfShippingMethodAssociatedWithRuleCanBeAccessed(): void
    {
        $defaultContext = Context::createDefaultContext();

        $this->ruleRepository->create($this->rule, $defaultContext);

        $criteria = new Criteria([$this->ruleId]);
        $criteria->addAssociation('shippingMethods');

        $searchResult = $this->ruleRepository->search($criteria, $defaultContext)->getEntities()->first();

        static::assertNotNull($searchResult);

        static::assertSame($this->ruleId, $searchResult->getId());
        static::assertSame(
            $this->rule[0]['shippingMethods'][0]['id'],
            $searchResult->getShippingMethods()?->first()?->getId()
        );
    }

    public function testIfShippingMethodCanBeAddedToRule(): void
    {
        $defaultContext = Context::createDefaultContext();
        $this->ruleRepository->create($this->rule, $defaultContext);

        $additionalShippingMethod = [
            'id' => Uuid::randomHex(),
            'type' => 1,
            'bindShippingfree' => true,
            'deliveryTime' => $this->createDeliveryTimeData(),
            'created_at' => new \DateTime(),
            'name' => 'additional ShippingMethod',
            'technicalName' => 'shipping_additional',
        ];

        $this->ruleRepository->update([[
            'id' => $this->ruleId,
            'shippingMethods' => [
                $additionalShippingMethod,
            ],
        ]], $defaultContext);

        $criteria = new Criteria([$this->ruleId]);
        $criteria->addAssociation('shippingMethods');

        $searchResult = $this->ruleRepository->search($criteria, $defaultContext)->getEntities()->first();

        static::assertNotNull($searchResult);
        static::assertIsIterable($searchResult->getShippingMethods());
        static::assertCount(2, $searchResult->getShippingMethods());
    }

    public function testIfRuleCanBeRemoved(): void
    {
        $defaultContext = Context::createDefaultContext();
        $this->ruleRepository->create($this->rule, $defaultContext);

        $this->expectException(RestrictDeleteViolationException::class);
        $this->ruleRepository->delete([['id' => $this->ruleId]], $defaultContext);
    }

    public function testRulesCanBeAccessedFromShippingMethod(): void
    {
        $defaultContext = Context::createDefaultContext();
        $this->ruleRepository->create($this->rule, $defaultContext);

        $criteria = new Criteria([$this->rule[0]['shippingMethods'][0]['id']]);
        $criteria->addAssociation('availabilityRule');

        /** @var EntityRepository<ShippingMethodCollection> $shippingRepo */
        $shippingRepo = $this->getContainer()->get('shipping_method.repository');
        $searchResult = $shippingRepo->search($criteria, $defaultContext)->getEntities()->first();

        static::assertNotNull($searchResult);
        static::assertSame($this->ruleId, $searchResult->getAvailabilityRule()?->getId());
    }

    public function testRuleAssociationsStayLikeLinked(): void
    {
        $defaultContext = Context::createDefaultContext();
        $rules = $this->createComplicatedTestData();

        $this->ruleRepository->create($rules, $defaultContext);

        $criteria1 = new Criteria(['id' => $this->ruleId]);
        $criteria1->addAssociation('shippingMethods');

        $criteria2 = new Criteria(['id' => $rules[1]['id']]);
        $criteria2->addAssociation('shippingMethods');

        $rule1 = $this->ruleRepository->search($criteria1, $defaultContext)->getEntities()->first();
        $rule2 = $this->ruleRepository->search($criteria2, $defaultContext)->getEntities()->last();

        static::assertNotNull($rule1);
        static::assertNotNull($rule2);

        $rule1ShippingMethods = $rule1->getShippingMethods();
        $rule2ShippingMethods = $rule2->getShippingMethods();

        static::assertNotNull($rule1ShippingMethods);
        static::assertNotNull($rule2ShippingMethods);

        static::assertNotSame($rule1, $rule2);
        static::assertNotSame($rule1ShippingMethods->first(), $rule1ShippingMethods->last());

        static::assertCount(1, $rule1ShippingMethods->filterByProperty('active', true));
        static::assertCount(1, $rule1ShippingMethods->filterByProperty('active', false));

        static::assertCount(1, $rule2ShippingMethods->filterByProperty('active', true));
        static::assertCount(0, $rule2ShippingMethods->filterByProperty('active', false));

        static::assertCount(2, $rule1ShippingMethods);
        static::assertCount(1, $rule2ShippingMethods);
    }

    private function prepareSimpleTestData(): void
    {
        $this->ruleId = Uuid::randomHex();

        $shippingMethod = [
            'id' => Uuid::randomHex(),
            'type' => 1,
            'bindShippingfree' => false,
            'deliveryTime' => $this->createDeliveryTimeData(),
            'created_at' => new \DateTime(),
            'name' => 'test',
            'technicalName' => 'shipping_test',
        ];

        $this->rule = [
            [
                'id' => $this->ruleId,
                'name' => 'asd',
                'priority' => 2,
                'shippingMethods' => [
                    $shippingMethod,
                ],
            ],
        ];
    }

    /**
     * @return array<array<string, mixed>>
     */
    private function createComplicatedTestData(): array
    {
        $this->ruleId = Uuid::randomHex();

        $shippingMethods = [
            [
                'id' => Uuid::randomHex(),
                'type' => 1,
                'bindShippingfree' => false,
                'deliveryTime' => $this->createDeliveryTimeData(),
                'active' => true,
                'created_at' => new \DateTime(),
                'name' => 'test',
                'technicalName' => 'shipping_test',
            ],
            [
                'id' => Uuid::randomHex(),
                'type' => 1,
                'active' => true,
                'bindShippingfree' => true,
                'deliveryTime' => $this->createDeliveryTimeData(),
                'created_at' => new \DateTime('-2 days'),
                'name' => 'shippingFreeShipping',
                'technicalName' => 'shipping_freeshipping',
            ],
            [
                'id' => Uuid::randomHex(),
                'type' => 1,
                'active' => false,
                'bindShippingfree' => false,
                'deliveryTime' => $this->createDeliveryTimeData(),
                'created_at' => new \DateTime(),
                'name' => 'unused shippingMethod',
                'technicalName' => 'shipping_unused',
            ],
        ];

        $rules = [
            [
                'id' => $this->ruleId,
                'name' => 'asd',
                'priority' => 2,
                'shippingMethods' => [
                    $shippingMethods[0],
                    $shippingMethods[2],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'name' => 'test',
                'priority' => 90,
                'shippingMethods' => [
                    $shippingMethods[1],
                ],
            ],
        ];

        return $rules;
    }

    /**
     * @return array{id: string, name: string, min: int, max: int, unit: string}
     */
    private function createDeliveryTimeData(): array
    {
        return [
            'id' => Uuid::randomHex(),
            'name' => 'test',
            'min' => 1,
            'max' => 90,
            'unit' => DeliveryTimeEntity::DELIVERY_TIME_DAY,
        ];
    }
}
