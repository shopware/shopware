<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\PaymentHandler\TestPaymentHandler;

/**
 * @internal
 */
#[Package('services-settings')]
class PaymentMethodRuleAccessibleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
    }

    public function testIfPaymentMethodAssociatedWithRuleCanBeAccessed(): void
    {
        $defaultContext = Context::createDefaultContext();

        $rule = $this->createSimpleRule();
        $this->ruleRepository->create($rule, $defaultContext);
        $ruleId = $rule[0]['id'];

        $criteria = new Criteria([$ruleId]);
        $criteria->addAssociation('paymentMethods');

        $searchedRule = $this->ruleRepository->search($criteria, $defaultContext)->getEntities()->first();
        static::assertNotNull($searchedRule);

        static::assertSame($ruleId ?? null, $searchedRule->getId());
        static::assertSame(
            $rule[0]['paymentMethods'][0]['id'] ?? null,
            $searchedRule->getPaymentMethods()?->first()?->getId()
        );
    }

    public function testIfPaymentMethodCanBeAddedToRule(): void
    {
        $defaultContext = Context::createDefaultContext();
        $rule = $this->createSimpleRule();
        $ruleId = $rule[0]['id'];

        $this->ruleRepository->create($rule, $defaultContext);

        $additionalPaymentMethod = [
            'id' => Uuid::randomHex(),
            'handlerIdentifier' => TestPaymentHandler::class,
            'created_at' => new \DateTime(),
            'name' => 'additional PaymentMethod',
            'technicalName' => 'payment_additional',
        ];

        $this->ruleRepository->update([[
            'id' => $ruleId,
            'paymentMethods' => [
                $additionalPaymentMethod,
            ],
        ]], $defaultContext);

        $criteria = new Criteria([$ruleId]);
        $criteria->addAssociation('paymentMethods');

        $searchedRule = $this->ruleRepository->search($criteria, $defaultContext)->getEntities()->first();
        static::assertNotNull($searchedRule);

        static::assertCount(2, $searchedRule->getPaymentMethods() ?? new PaymentMethodCollection());
    }

    public function testIfRuleWithAssocCanNotBeRemoved(): void
    {
        $defaultContext = Context::createDefaultContext();
        $rule = $this->createSimpleRule();
        $this->ruleRepository->create($rule, $defaultContext);

        static::expectException(RestrictDeleteViolationException::class);
        $this->ruleRepository->delete([['id' => $rule[0]['id']]], $defaultContext);
    }

    public function testIfRuleWithoutAssocCanBeRemoved(): void
    {
        $defaultContext = Context::createDefaultContext();
        $rule = $this->createSimpleRuleWithoutAssoc();
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

        $criteria = new Criteria([$rule[0]['paymentMethods'][0]['id'] ?? '']);
        $criteria->addAssociation('availabilityRule');

        /** @var EntityRepository<PaymentMethodCollection> $paymentMethodRepo */
        $paymentMethodRepo = $this->getContainer()->get('payment_method.repository');
        $paymentMethod = $paymentMethodRepo->search($criteria, $defaultContext)->getEntities()->first();
        static::assertNotNull($paymentMethod);

        static::assertSame($rule[0]['id'] ?? null, $paymentMethod->getAvailabilityRule()?->getId());
    }

    public function testRuleAssociationsStayLikeLinked(): void
    {
        $defaultContext = Context::createDefaultContext();
        $ruleId = Uuid::randomHex();
        $rules = $this->createComplexRules($ruleId);

        $this->ruleRepository->create($rules, $defaultContext);

        $criteria1 = new Criteria(['id' => $ruleId]);
        $criteria1->addAssociation('paymentMethods');

        $criteria2 = new Criteria(['id' => $rules[1]['id']]);
        $criteria2->addAssociation('paymentMethods');

        $rule1 = $this->ruleRepository->search($criteria1, $defaultContext)->getEntities()->first();
        static::assertInstanceOf(RuleEntity::class, $rule1);
        $rule2 = $this->ruleRepository->search($criteria2, $defaultContext)->getEntities()->first();
        static::assertInstanceOf(RuleEntity::class, $rule2);
        $paymentMethods1 = $rule1->getPaymentMethods();
        static::assertNotNull($paymentMethods1);
        $paymentMethods2 = $rule2->getPaymentMethods();
        static::assertNotNull($paymentMethods2);

        static::assertNotSame($rule1, $rule2);
        static::assertNotSame($paymentMethods1->first(), $paymentMethods1->last());

        static::assertCount(1, $paymentMethods1->filterByProperty('active', true));
        static::assertCount(1, $paymentMethods1->filterByProperty('active', false));

        static::assertCount(1, $paymentMethods2->filterByProperty('active', true));
        static::assertCount(0, $paymentMethods2->filterByProperty('active', false));

        static::assertCount(2, $paymentMethods1);
        static::assertCount(1, $paymentMethods2);
    }

    /**
     * @return mixed[]
     */
    private function createSimpleRule(): array
    {
        return [
            [
                'id' => Uuid::randomHex(),
                'name' => 'asd',
                'priority' => 2,
                'paymentMethods' => [
                    [
                        'id' => Uuid::randomHex(),
                        'handlerIdentifier' => TestPaymentHandler::class,
                        'created_at' => new \DateTime(),
                        'name' => 'test',
                        'technicalName' => 'payment_test',
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<array{id: string, name: string, priority: int}>
     */
    private function createSimpleRuleWithoutAssoc(): array
    {
        return [
            [
                'id' => Uuid::randomHex(),
                'name' => 'asd',
                'priority' => 2,
            ],
        ];
    }

    /**
     * @return mixed[]
     */
    private function createComplexRules(string $ruleId): array
    {
        return [
            [
                'id' => $ruleId,
                'name' => 'asd',
                'priority' => 2,
                'paymentMethods' => [
                    [
                        'id' => Uuid::randomHex(),
                        'handlerIdentifier' => TestPaymentHandler::class,
                        'active' => true,
                        'created_at' => new \DateTime(),
                        'name' => 'test',
                        'technicalName' => 'payment_test',
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'handlerIdentifier' => TestPaymentHandler::class,
                        'active' => false,
                        'created_at' => new \DateTime(),
                        'name' => 'unused paymentMethod',
                        'technicalName' => 'payment_unused',
                    ],
                ],
            ],
            [
                'id' => Uuid::randomHex(),
                'name' => 'test',
                'priority' => 90,
                'paymentMethods' => [
                    [
                        'id' => Uuid::randomHex(),
                        'handlerIdentifier' => TestPaymentHandler::class,
                        'active' => true,
                        'created_at' => new \DateTime('-2 days'),
                        'name' => 'paymentFreePayment',
                        'technicalName' => 'payment_freepayment',
                    ],
                ],
            ],
        ];
    }
}
