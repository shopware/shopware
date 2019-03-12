<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class PaymentMethodRuleAccessibleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $ruleRepository;

    public function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
    }

    public function testIfPaymentMethodAssociatedWithRuleCanBeAccessed(): void
    {
        $defaultContext = Context::createDefaultContext();

        $rule = $this->createSimpleRule();
        $this->ruleRepository->create($rule, $defaultContext);

        $criteria = new Criteria([$rule[0]['id']]);
        $criteria->addAssociation('paymentMethods');

        $searchResult = $this->ruleRepository->search($criteria, $defaultContext);

        static::assertSame($rule[0]['id'], $searchResult->first()->getId());
        static::assertSame(
            $rule[0]['paymentMethods'][0]['id'],
            $searchResult->first()->getPaymentMethods()->first()->getId()
        );
    }

    public function testIfPaymentMethodCanBeAddedToRule(): void
    {
        $defaultContext = Context::createDefaultContext();
        $rule = $this->createSimpleRule();

        $this->ruleRepository->create($rule, $defaultContext);

        $additionalPaymentMethod = [
            'id' => Uuid::uuid4()->getHex(),
            'type' => 1,
            'technicalName' => 'techTest' . hash('sha512', Uuid::uuid4()->getHex()),
            'created_at' => new \DateTime(),
            'name' => 'additional PaymentMethod',
        ];

        $this->ruleRepository->update([[
            'id' => $rule[0]['id'],
            'paymentMethods' => [
                $additionalPaymentMethod,
            ],
        ]], $defaultContext);

        $criteria = new Criteria([$rule[0]['id']]);
        $criteria->addAssociation('paymentMethods');

        /* @var PaymentMethodCollection $searchResult */
        $searchResult = $this->ruleRepository->search($criteria, $defaultContext);

        static::assertCount(2, $searchResult->first()->getPaymentMethods());
    }

    public function testIfRuleCanBeRemoved(): void
    {
        $defaultContext = Context::createDefaultContext();
        $rule = $this->createSimpleRule();
        $this->ruleRepository->create($rule, $defaultContext);

        $this->ruleRepository->delete([['id' => $rule[0]['id']]], $defaultContext);

        $criteria = new Criteria([$rule[0]['id']]);
        $searchResult = $this->ruleRepository->search($criteria, $defaultContext);

        static::assertCount(0, $searchResult);
    }

    public function testRulesCanBeAccessedFromPaymentMethod(): void
    {
        $defaultContext = Context::createDefaultContext();
        $rule = $this->createSimpleRule();

        $this->ruleRepository->create($rule, $defaultContext);

        $criteria = new Criteria([$rule[0]['paymentMethods'][0]['id']]);
        $criteria->addAssociation('availabilityRules');

        $searchResult = $this->getContainer()->get('payment_method.repository')->search($criteria, $defaultContext);

        static::assertSame($rule[0]['id'], $searchResult->first()->getAvailabilityRules()->first()->getId());
    }

    public function testRuleAssociationsStayLikeLinked(): void
    {
        $defaultContext = Context::createDefaultContext();
        $ruleId = Uuid::uuid4()->getHex();
        $rules = $this->createComplexRules($ruleId);

        $this->ruleRepository->create($rules, $defaultContext);

        $criteria1 = new Criteria(['id' => $ruleId]);
        $criteria1->addAssociation('paymentMethods');

        $criteria2 = new Criteria(['id' => $rules[1]['id']]);
        $criteria2->addAssociation('paymentMethods');

        $rule1 = $this->ruleRepository->search($criteria1, $defaultContext);
        $rule2 = $this->ruleRepository->search($criteria2, $defaultContext);

        static::assertNotSame($rule1->first(), $rule2->first());
        static::assertNotSame($rule1->first()->getPaymentMethods()->first(), $rule1->first()->getPaymentMethods()->last());

        static::assertCount(1, $rule1->first()->getPaymentMethods()->filterByProperty('active', true));
        static::assertCount(1, $rule1->first()->getPaymentMethods()->filterByProperty('active', false));

        static::assertCount(1, $rule2->first()->getPaymentMethods()->filterByProperty('active', true));
        static::assertCount(0, $rule2->first()->getPaymentMethods()->filterByProperty('active', false));

        static::assertCount(2, $rule1->first()->getPaymentMethods());
        static::assertCount(1, $rule2->first()->getPaymentMethods());
    }

    private function createSimpleRule(): array
    {
        return [
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'asd',
                'priority' => 2,
                'paymentMethods' => [
                    [
                        'id' => Uuid::uuid4()->getHex(),
                        'type' => 1,
                        'technicalName' => $this->getUniqueTechName(),
                        'created_at' => new \DateTime(),
                        'name' => 'test',
                    ],
                ],
            ],
        ];
    }

    private function createComplexRules(string $ruleId): array
    {
        return [
            [
                'id' => $ruleId,
                'name' => 'asd',
                'priority' => 2,
                'paymentMethods' => [
                    [
                        'id' => Uuid::uuid4()->getHex(),
                        'type' => 1,
                        'technicalName' => $this->getUniqueTechName(),
                        'active' => true,
                        'created_at' => new \DateTime(),
                        'name' => 'test',
                    ],
                    [
                        'id' => Uuid::uuid4()->getHex(),
                        'type' => 1,
                        'active' => false,
                        'technicalName' => $this->getUniqueTechName(),
                        'created_at' => new \DateTime(),
                        'name' => 'unused paymentMethod',
                    ],
                ],
            ],
            [
                'id' => Uuid::uuid4()->getHex(),
                'name' => 'test',
                'priority' => 90,
                'paymentMethods' => [
                    [
                        'id' => Uuid::uuid4()->getHex(),
                        'type' => 1,
                        'active' => true,
                        'technicalName' => $this->getUniqueTechName(),
                        'created_at' => new \DateTime('-2 days'),
                        'name' => 'paymentFreePayment',
                    ],
                ],
            ],
        ];
    }

    private function getUniqueTechName(): string
    {
        return 'techTest' . hash('sha512', Uuid::uuid4()->getHex());
    }
}
