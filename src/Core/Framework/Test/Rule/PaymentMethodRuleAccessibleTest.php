<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\AsyncTestPaymentHandler;
use Shopware\Core\Checkout\Test\Payment\Handler\V630\SyncTestPaymentHandler;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\RestrictDeleteViolationException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('business-ops')]
class PaymentMethodRuleAccessibleTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $ruleRepository;

    protected function setUp(): void
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

        /** @var RuleEntity $searchedRule */
        $searchedRule = $this->ruleRepository->search($criteria, $defaultContext)->first();

        static::assertSame($rule[0]['id'], $searchedRule->getId());
        static::assertSame(
            $rule[0]['paymentMethods'][0]['id'],
            $searchedRule->getPaymentMethods()->first()->getId()
        );
    }

    public function testIfPaymentMethodCanBeAddedToRule(): void
    {
        $defaultContext = Context::createDefaultContext();
        $rule = $this->createSimpleRule();

        $this->ruleRepository->create($rule, $defaultContext);

        $additionalPaymentMethod = [
            'id' => Uuid::randomHex(),
            'handlerIdentifier' => AsyncTestPaymentHandler::class,
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

        /** @var RuleEntity $searchedRule */
        $searchedRule = $this->ruleRepository->search($criteria, $defaultContext)->first();

        static::assertCount(2, $searchedRule->getPaymentMethods());
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

        $criteria = new Criteria([$rule[0]['paymentMethods'][0]['id']]);
        $criteria->addAssociation('availabilityRule');

        /** @var PaymentMethodEntity $paymentMethod */
        $paymentMethod = $this->getContainer()->get('payment_method.repository')->search($criteria, $defaultContext)->first();

        static::assertSame($rule[0]['id'], $paymentMethod->getAvailabilityRule()->getId());
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

        /** @var RuleEntity $rule1 */
        $rule1 = $this->ruleRepository->search($criteria1, $defaultContext)->first();
        /** @var RuleEntity $rule2 */
        $rule2 = $this->ruleRepository->search($criteria2, $defaultContext)->first();

        static::assertNotSame($rule1, $rule2);
        static::assertNotSame($rule1->getPaymentMethods()->first(), $rule1->getPaymentMethods()->last());

        static::assertCount(1, $rule1->getPaymentMethods()->filterByProperty('active', true));
        static::assertCount(1, $rule1->getPaymentMethods()->filterByProperty('active', false));

        static::assertCount(1, $rule2->getPaymentMethods()->filterByProperty('active', true));
        static::assertCount(0, $rule2->getPaymentMethods()->filterByProperty('active', false));

        static::assertCount(2, $rule1->getPaymentMethods());
        static::assertCount(1, $rule2->getPaymentMethods());
    }

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
                        'handlerIdentifier' => SyncTestPaymentHandler::class,
                        'created_at' => new \DateTime(),
                        'name' => 'test',
                    ],
                ],
            ],
        ];
    }

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
                        'handlerIdentifier' => SyncTestPaymentHandler::class,
                        'active' => true,
                        'created_at' => new \DateTime(),
                        'name' => 'test',
                    ],
                    [
                        'id' => Uuid::randomHex(),
                        'handlerIdentifier' => AsyncTestPaymentHandler::class,
                        'active' => false,
                        'created_at' => new \DateTime(),
                        'name' => 'unused paymentMethod',
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
                        'handlerIdentifier' => SyncTestPaymentHandler::class,
                        'active' => true,
                        'created_at' => new \DateTime('-2 days'),
                        'name' => 'paymentFreePayment',
                    ],
                ],
            ],
        ];
    }
}
