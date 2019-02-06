<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ShippingMethodRuleAccessibleTest extends TestCase
{
    use IntegrationTestBehaviour;
    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    /**
     * @var array
     */
    private $rule;

    /**
     * @var string
     */
    private $ruleId;

    public function setUp(): void
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

        $searchResult = $this->ruleRepository->search($criteria, $defaultContext);

        static::assertSame($this->ruleId, $searchResult->first()->getId());
        static::assertSame(
            $this->rule[0]['shippingMethods'][0]['id'],
            $searchResult->first()->getShippingMethods()->first()->getId()
        );
    }

    public function testIfShippingMethodCanBeAddedToRule(): void
    {
        $defaultContext = Context::createDefaultContext();
        $this->ruleRepository->create($this->rule, $defaultContext);

        $additionalShippingMethod = [
            'id' => Uuid::uuid4()->getHex(),
            'type' => 1,
            'bindShippingfree' => true,
            'created_at' => new \DateTime(),
            'name' => 'additional ShippingMethod',
        ];

        $this->ruleRepository->update([[
            'id' => $this->ruleId,
            'shippingMethods' => [
                $additionalShippingMethod,
            ],
        ]], $defaultContext);

        $criteria = new Criteria([$this->ruleId]);
        $criteria->addAssociation('shippingMethods');

        /* @var ShippingMethodCollection $searchResult */
        $searchResult = $this->ruleRepository->search($criteria, $defaultContext);

        static::assertCount(2, $searchResult->first()->getShippingMethods());
    }

    public function testIfRuleCanBeRemoved(): void
    {
        $defaultContext = Context::createDefaultContext();
        $this->ruleRepository->create($this->rule, $defaultContext);

        $this->ruleRepository->delete([['id' => $this->ruleId]], $defaultContext);

        $criteria = new Criteria([$this->ruleId]);
        $searchResult = $this->ruleRepository->search($criteria, $defaultContext);

        static::assertCount(0, $searchResult);
    }

    public function testRulesCanBeAccessedFromShippingMethod(): void
    {
        $defaultContext = Context::createDefaultContext();
        $this->ruleRepository->create($this->rule, $defaultContext);

        $criteria = new Criteria([$this->rule[0]['shippingMethods'][0]['id']]);
        $criteria->addAssociation('availabilityRules');

        $searchResult = $this->getContainer()->get('shipping_method.repository')->search($criteria, $defaultContext);

        static::assertSame($this->ruleId, $searchResult->first()->getAvailabilityRules()->first()->getId());
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

        $rule1 = $this->ruleRepository->search($criteria1, $defaultContext);
        $rule2 = $this->ruleRepository->search($criteria2, $defaultContext);

        static::assertNotSame($rule1->first(), $rule2->first());
        static::assertNotSame($rule1->first()->getShippingMethods()->first(), $rule1->first()->getShippingMethods()->last());

        static::assertCount(1, $rule1->first()->getShippingMethods()->filterByProperty('active', true));
        static::assertCount(1, $rule1->first()->getShippingMethods()->filterByProperty('active', false));

        static::assertCount(1, $rule2->first()->getShippingMethods()->filterByProperty('active', true));
        static::assertCount(0, $rule2->first()->getShippingMethods()->filterByProperty('active', false));

        static::assertCount(2, $rule1->first()->getShippingMethods());
        static::assertCount(1, $rule2->first()->getShippingMethods());
    }

    private function prepareSimpleTestData(): void
    {
        $this->ruleId = Uuid::uuid4()->getHex();

        $shippingMethod = [
            'id' => Uuid::uuid4()->getHex(),
            'type' => 1,
            'bindShippingfree' => false,
            'created_at' => new \DateTime(),
            'name' => 'test',
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

    private function createComplicatedTestData(): array
    {
        $this->ruleId = Uuid::uuid4()->getHex();

        $shippingMethods = [
            [
                'id' => Uuid::uuid4()->getHex(),
                'type' => 1,
                'bindShippingfree' => false,
                'active' => true,
                'created_at' => new \DateTime(),
                'name' => 'test',
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'type' => 1,
                'active' => true,
                'bindShippingfree' => true,
                'created_at' => new \DateTime('-2 days'),
                'name' => 'shippingFreeShipping',
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'type' => 1,
                'active' => false,
                'bindShippingfree' => false,
                'created_at' => new \DateTime(),
                'name' => 'unused shippingMethod',
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
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test',
                'priority' => 90,
                'shippingMethods' => [
                    $shippingMethods[1],
                ],
            ],
        ];

        return $rules;
    }
}
