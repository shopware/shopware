<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Customer\Rule\CustomerGroupRule;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppStateService;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycle;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\ScriptRule;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('business-ops')]
class ScriptRuleTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private EntityRepository $ruleRepository;

    private EntityRepository $conditionRepository;

    private EntityRepository $appRepository;

    private AppStateService $appStateService;

    private AppLifecycle $appLifecycle;

    private Context $context;

    private string $scriptId;

    private string $appId;

    protected function setUp(): void
    {
        $this->ruleRepository = $this->getContainer()->get('rule.repository');
        $this->conditionRepository = $this->getContainer()->get('rule_condition.repository');
        $this->appRepository = $this->getContainer()->get('app.repository');
        $this->appStateService = $this->getContainer()->get(AppStateService::class);
        $this->appLifecycle = $this->getContainer()->get(AppLifecycle::class);
        $this->context = Context::createDefaultContext();
    }

    /**
     * @runInSeparateProcess
     *
     * @param array<string, string> $values
     *
     * @dataProvider scriptProvider
     */
    public function testRuleScriptExecution(string $path, array $values, bool $expectedTrue): void
    {
        $script = file_get_contents(__DIR__ . $path);
        $scope = new CheckoutRuleScope($this->createSalesChannelContext());
        $rule = new ScriptRule();

        $rule->assign([
            'values' => $values,
            'script' => $script,
            'debug' => false,
            'cacheDir' => $this->getContainer()->getParameter('kernel.cache_dir'),
        ]);

        if ($expectedTrue) {
            static::assertTrue($rule->match($scope));
        } else {
            static::assertFalse($rule->match($scope));
        }
    }

    public static function scriptProvider(): \Generator
    {
        yield 'simple script return true' => ['/_fixture/scripts/simple.twig', ['test' => 'foo'], true];
        yield 'simple script return false' => ['/_fixture/scripts/simple.twig', ['test' => 'bar'], false];
    }

    /**
     * @runInSeparateProcess
     *
     * @depends testRuleScriptExecution
     */
    public function testRuleScriptIsCached(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule = new ScriptRule();

        $rule->assign([
            'script' => '{% return true %}',
            'values' => [],
            'lastModified' => (new \DateTimeImmutable())->sub(new \DateInterval('P1D')),
            'debug' => false,
            'cacheDir' => $this->getContainer()->getParameter('kernel.cache_dir'),
        ]);

        static::assertFalse($rule->match($scope));
    }

    /**
     * @runInSeparateProcess
     *
     * @depends testRuleScriptIsCached
     */
    public function testCachedRuleScriptIsInvalidated(): void
    {
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $scope = new CheckoutRuleScope($salesChannelContext);
        $rule = new ScriptRule();

        $rule->assign([
            'script' => '{% return true %}',
            'values' => [],
            'debug' => false,
            'cacheDir' => $this->getContainer()->getParameter('kernel.cache_dir'),
        ]);

        static::assertTrue($rule->match($scope));
    }

    public function testRuleIsConsistent(): void
    {
        $this->installApp();
        $ruleId = Uuid::randomHex();
        $expectedTrueScope = $this->getCheckoutScope($ruleId);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();
        $customer->setGroupId(Uuid::randomHex());
        $salesChannelContext->method('getCustomer')->willReturn($customer);
        $expectedFalseScope = new CheckoutRuleScope($salesChannelContext);

        /** @var RuleEntity $rule */
        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->get($ruleId);
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);

        static::assertFalse($payload->match($expectedFalseScope));

        static::assertTrue($payload->match($expectedTrueScope));
    }

    public function testRuleValidation(): void
    {
        $this->installApp();

        try {
            $ruleId = Uuid::randomHex();
            $this->ruleRepository->create(
                [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
                Context::createDefaultContext()
            );

            $groupId = Uuid::randomHex();
            $id = Uuid::randomHex();
            $this->conditionRepository->create([
                [
                    'id' => $id,
                    'type' => (new ScriptRule())->getName(),
                    'ruleId' => $ruleId,
                    'scriptId' => $this->scriptId,
                    'value' => [
                        'operator' => 'foo',
                    ],
                ],
            ], $this->context);

            static::fail('Exception was not thrown');
        } catch (WriteException $stackException) {
            $exceptions = iterator_to_array($stackException->getErrors());
            static::assertCount(2, $exceptions);
            static::assertSame('/0/value/operator', $exceptions[0]['source']['pointer']);
            static::assertSame(Choice::NO_SUCH_CHOICE_ERROR, $exceptions[0]['code']);
            static::assertSame('/0/value/customerGroupIds', $exceptions[1]['source']['pointer']);
            static::assertSame(NotBlank::IS_BLANK_ERROR, $exceptions[1]['code']);
        }
    }

    public function testRuleWithInactiveScript(): void
    {
        $this->installApp();
        $ruleId = Uuid::randomHex();
        $scope = $this->getCheckoutScope($ruleId);

        $this->appStateService->deactivateApp($this->appId, $this->context);

        /** @var RuleEntity $rule */
        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->get($ruleId);
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);
        static::assertFalse($payload->match($scope));

        $this->appStateService->activateApp($this->appId, $this->context);

        /** @var RuleEntity $rule */
        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->get($ruleId);
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);

        static::assertTrue($payload->match($scope));
    }

    public function testRuleWithUninstalledApp(): void
    {
        $this->installApp();
        $ruleId = Uuid::randomHex();
        $scope = $this->getCheckoutScope($ruleId);

        /** @var RuleEntity $rule */
        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->get($ruleId);
        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);
        static::assertTrue($payload->match($scope));

        $this->appLifecycle->delete('test', ['id' => $this->appId], $this->context);

        /** @var RuleEntity $rule */
        $rule = $this->ruleRepository->search(new Criteria([$ruleId]), $this->context)->get($ruleId);

        $payload = $rule->getPayload();
        static::assertInstanceOf(Rule::class, $payload);
        static::assertFalse($payload->match($scope));
    }

    private function getCheckoutScope(string $ruleId): CheckoutRuleScope
    {
        $this->ruleRepository->create(
            [['id' => $ruleId, 'name' => 'Demo rule', 'priority' => 1]],
            Context::createDefaultContext()
        );

        $groupId = Uuid::randomHex();
        $id = Uuid::randomHex();
        $this->conditionRepository->create([
            [
                'id' => $id,
                'type' => (new ScriptRule())->getName(),
                'ruleId' => $ruleId,
                'scriptId' => $this->scriptId,
                'value' => [
                    'customerGroupIds' => [Uuid::randomHex(), $groupId],
                    'operator' => CustomerGroupRule::OPERATOR_EQ,
                ],
            ],
        ], $this->context);

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $customer = new CustomerEntity();

        $customer->setGroupId($groupId);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        return new CheckoutRuleScope($salesChannelContext);
    }

    private function installApp(): void
    {
        $fixturesPath = __DIR__ . '/../../../../../tests/integration/php/Core/Framework/App/Manifest/_fixtures';

        $manifest = Manifest::createFromXmlFile($fixturesPath . '/test/manifest.xml');
        $this->appLifecycle->install($manifest, false, $this->context);
        /** @var AppEntity $app */
        $app = $this->appRepository->search((new Criteria())->addAssociation('scriptConditions'), $this->context)->first();

        $this->appId = $app->getId();
        $this->appStateService->activateApp($this->appId, $this->context);
        $conditions = $app->getScriptConditions();
        static::assertInstanceOf(AppScriptConditionCollection::class, $conditions);
        $condition = $conditions->first();
        static::assertInstanceOf(AppScriptConditionEntity::class, $condition);
        $this->scriptId = $condition->getId();
    }

    private function createSalesChannelContext(): SalesChannelContext
    {
        $salesChannelContextFactory = $this->getContainer()->get(SalesChannelContextFactory::class);

        return $salesChannelContextFactory->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }
}
