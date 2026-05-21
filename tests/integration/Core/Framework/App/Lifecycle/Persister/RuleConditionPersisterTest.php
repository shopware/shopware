<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopware\Core\Framework\App\Lifecycle\Persister\RuleConditionPersister;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Tests\Integration\Core\Framework\App\AppFixture;

/**
 * @internal
 */
class RuleConditionPersisterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const MANIFEST = __DIR__ . '/_fixtures/rule-condition-constraints/manifest.xml';

    private const UPDATED_MANIFEST = __DIR__ . '/_fixtures/rule-condition-constraints-updated/manifest.xml';

    private RuleConditionPersister $persister;

    private AppFixture $appFixture;

    /**
     * @var EntityRepository<AppScriptConditionCollection>
     */
    private EntityRepository $scriptConditionRepository;

    protected function setUp(): void
    {
        $this->persister = static::getContainer()->get(RuleConditionPersister::class);
        $this->scriptConditionRepository = static::getContainer()->get('app_script_condition.repository');

        $appFixture = static::getContainer()->get(AppFixture::class);
        \assert($appFixture instanceof AppFixture);
        $this->appFixture = $appFixture;
    }

    public function testPersistAndUpdateSavesRuleConditions(): void
    {
        $manifest = $this->appFixture->loadManifest(self::MANIFEST);
        $app = $this->appFixture->createApp($manifest);
        $appId = $app->getId();

        $this->persister->persist($this->appFixture->createInstallContext($app, $manifest));

        $criteria = (new Criteria())->addFilter(new EqualsFilter('appId', $appId));
        $scriptConditions = $this->scriptConditionRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(14, $scriptConditions);

        foreach ($scriptConditions as $scriptCondition) {
            static::assertStringContainsString('app\withRuleConditions_', $scriptCondition->getIdentifier());
            static::assertStringContainsString('{% return true %}', (string) $scriptCondition->getScript());
            static::assertIsArray($scriptCondition->getConfig());

            $this->assertScriptConditionFieldConfig($scriptCondition);
        }

        $updatedManifest = $this->appFixture->loadManifest(self::UPDATED_MANIFEST);
        $this->persister->persist($this->appFixture->createUpdateContext($app, $updatedManifest));

        $scriptConditions = $this->scriptConditionRepository->search($criteria, Context::createDefaultContext())->getEntities();

        static::assertCount(1, $scriptConditions);
        $appScriptConditionEntity = $scriptConditions->first();
        static::assertNotNull($appScriptConditionEntity);
        $identifier = $appScriptConditionEntity->getIdentifier();
        static::assertIsString($identifier);
        static::assertSame('app\withRuleConditions_testcondition0', $identifier);

        $constraints = $appScriptConditionEntity->getConstraints();
        static::assertIsArray($constraints);
        static::assertArrayHasKey('number', $constraints);

        $config = $appScriptConditionEntity->getConfig();
        static::assertIsArray($config);
        static::assertCount(1, $config);
        static::assertArrayHasKey(0, $config);
        static::assertIsArray($config[0]);
        static::assertArrayHasKey('type', $config[0]);
        static::assertSame('int', $config[0]['type']);
    }

    private function assertScriptConditionFieldConfig(AppScriptConditionEntity $scriptCondition): void
    {
        $constraints = $scriptCondition->getConstraints();
        static::assertIsArray($constraints);

        switch ($scriptCondition->getIdentifier()) {
            case 'app\withRuleConditions_testcondition0':
                static::assertArrayHasKey('operator', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('select', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition1':
                static::assertArrayHasKey('customerGroupIds', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('entity', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition2':
                static::assertArrayHasKey('firstName', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('text', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition3':
                static::assertArrayHasKey('number', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('int', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition4':
                static::assertArrayHasKey('number', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('float', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition5':
                static::assertArrayHasKey('productId', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('entity', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition6':
                static::assertArrayHasKey('expected', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('bool', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition7':
                static::assertArrayHasKey('datetime', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('datetime', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition8':
                static::assertArrayHasKey('colorcode', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('text', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition9':
                static::assertArrayHasKey('mediaId', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('text', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition10':
                static::assertArrayHasKey('price', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('price', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition11':
                static::assertArrayHasKey('firstName', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('html', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition12':
                static::assertArrayHasKey('multiselection', $constraints);
                static::assertIsArray($scriptCondition->getConfig());
                static::assertArrayHasKey(0, $scriptCondition->getConfig());
                static::assertIsArray($scriptCondition->getConfig()[0]);
                static::assertArrayHasKey('type', $scriptCondition->getConfig()[0]);
                static::assertSame('select', $scriptCondition->getConfig()[0]['type']);

                break;
            case 'app\withRuleConditions_testcondition13':
                static::assertCount(0, $constraints);
                static::assertCount(0, $scriptCondition->getConfig() ?? []);

                break;
            default:
                static::fail(\sprintf('Did not expect to find app script condition with identifier %s', $scriptCondition->getIdentifier()));
        }
    }
}
