<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Lifecycle\Persister;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\Event\RuleConditionActivateEvent;
use Shopware\Core\Framework\App\Lifecycle\Persister\Event\RuleConditionDeactivateEvent;
use Shopware\Core\Framework\App\Lifecycle\Persister\Event\RuleConditionPersistEvent;
use Shopware\Core\Framework\App\Lifecycle\Persister\RuleConditionPersister;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\BoolField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\CustomFieldType;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\FloatField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\IntField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\MediaSelectionField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\MultiEntitySelectField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\MultiSelectField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\PriceField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\SingleEntitySelectField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\SingleSelectField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomField\CustomFieldTypes\TextField;
use Shopware\Core\Framework\App\Manifest\Xml\Meta\Metadata;
use Shopware\Core\Framework\App\Manifest\Xml\RuleCondition\RuleCondition;
use Shopware\Core\Framework\App\Manifest\Xml\RuleCondition\RuleConditions;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\Framework\Util\StaticFilesystem;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RuleConditionPersister::class)]
class RuleConditionPersisterTest extends TestCase
{
    private ScriptFileReader&MockObject $scriptReader;

    private CollectingEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        $this->scriptReader = $this->createMock(ScriptFileReader::class);
        $this->eventDispatcher = new CollectingEventDispatcher();
    }

    public function testPersistDispatchesEvent(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$this->buildApp(new AppScriptConditionCollection())])]);

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([]);

        $persister = $this->buildPersister($appRepository, $conditionRepository);
        $context = $this->buildLifecycleContext($this->createManifest([]));

        $persister->persist($context);

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(RuleConditionPersistEvent::class, $events[0]);
    }

    public function testPersistEventCarriesLifecycleContext(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$this->buildApp(new AppScriptConditionCollection())])]);

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([]);

        $persister = $this->buildPersister($appRepository, $conditionRepository);
        $context = $this->buildLifecycleContext($this->createManifest([]));

        $persister->persist($context);

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(RuleConditionPersistEvent::class, $events[0]);
        static::assertSame($context, $events[0]->getContext());
    }

    public function testPersistUpsertsNewConditions(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$this->buildApp(new AppScriptConditionCollection())])]);

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([]);

        $this->scriptReader->method('getScriptContent')->willReturn('script-content');

        $condition = RuleCondition::fromArray([
            'identifier' => 'my-condition',
            'name' => ['en-GB' => 'My Condition'],
            'script' => 'condition.twig',
            'constraints' => [],
        ]);

        $persister = $this->buildPersister($appRepository, $conditionRepository);
        $persister->persist($this->buildLifecycleContext($this->createManifest([$condition])));

        static::assertCount(1, $conditionRepository->upserts);
        static::assertSame('app\\TestApp_my-condition', $conditionRepository->upserts[0][0]['identifier']);
        static::assertSame('script-content', $conditionRepository->upserts[0][0]['script']);
    }

    public function testPersistUpdatesExistingConditionById(): void
    {
        $existingCondition = new AppScriptConditionEntity();
        $existingCondition->setId('existing-id');
        $existingCondition->setIdentifier('app\\TestApp_my-condition');
        $existingCondition->setActive(true);
        $existingConditions = new AppScriptConditionCollection([$existingCondition]);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$this->buildApp($existingConditions)])]);

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([]);

        $this->scriptReader->method('getScriptContent')->willReturn('content');

        $condition = RuleCondition::fromArray([
            'identifier' => 'my-condition',
            'name' => ['en-GB' => 'My Condition'],
            'script' => 'condition.twig',
            'constraints' => [],
        ]);

        $persister = $this->buildPersister($appRepository, $conditionRepository);
        $persister->persist($this->buildLifecycleContext($this->createManifest([$condition])));

        static::assertCount(1, $conditionRepository->upserts);
        static::assertSame('existing-id', $conditionRepository->upserts[0][0]['id']);
    }

    public function testPersistDeletesOrphanedConditions(): void
    {
        $orphan = new AppScriptConditionEntity();
        $orphan->setId('orphan-id');
        $orphan->setIdentifier('app\\TestApp_old-condition');
        $orphan->setActive(true);
        $existingConditions = new AppScriptConditionCollection([$orphan]);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$this->buildApp($existingConditions)])]);

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([]);

        $persister = $this->buildPersister($appRepository, $conditionRepository);
        $persister->persist($this->buildLifecycleContext($this->createManifest([])));

        static::assertEmpty($conditionRepository->upserts);
        static::assertCount(1, $conditionRepository->deletes);
        static::assertSame([['id' => 'orphan-id']], $conditionRepository->deletes[0]);
    }

    public function testPersistSkipsUpsertWhenNoConditionsInManifest(): void
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$this->buildApp(new AppScriptConditionCollection())])]);

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([]);

        $persister = $this->buildPersister($appRepository, $conditionRepository);
        $persister->persist($this->buildLifecycleContext($this->createManifest([])));

        static::assertEmpty($conditionRepository->upserts);
    }

    public function testActivateConditionScriptsDispatchesEvent(): void
    {
        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([[]]);

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($appRepo, $conditionRepository);
        $persister->activateConditionScripts('app-id', Context::createDefaultContext());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(RuleConditionActivateEvent::class, $events[0]);
    }

    public function testActivateConditionScriptsActivatesInactiveScripts(): void
    {
        $context = Context::createDefaultContext();

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([['script-id']]);

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($appRepo, $conditionRepository);
        $persister->activateConditionScripts('test-app-id', $context);

        static::assertCount(1, $conditionRepository->updates);
        static::assertSame([['id' => 'script-id', 'active' => true]], $conditionRepository->updates[0]);
    }

    public function testActivateConditionScriptsUpdateEmptyDataWhenNoneInactive(): void
    {
        $context = Context::createDefaultContext();

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([[]]);

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($appRepo, $conditionRepository);
        $persister->activateConditionScripts('app-id', $context);

        static::assertCount(1, $conditionRepository->updates);
        static::assertSame([], $conditionRepository->updates[0]);
    }

    public function testDeactivateConditionScriptsDispatchesEvent(): void
    {
        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([[]]);

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($appRepo, $conditionRepository);
        $persister->deactivateConditionScripts('app-id', Context::createDefaultContext());

        $events = $this->eventDispatcher->getEvents();
        static::assertCount(1, $events);
        static::assertInstanceOf(RuleConditionDeactivateEvent::class, $events[0]);
    }

    public function testDeactivateConditionScriptsDeactivatesActiveScripts(): void
    {
        $context = Context::createDefaultContext();

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([['script-id']]);

        /** @var StaticEntityRepository<AppCollection> $appRepo */
        $appRepo = new StaticEntityRepository([]);

        $persister = $this->buildPersister($appRepo, $conditionRepository);
        $persister->deactivateConditionScripts('test-app-id', $context);

        static::assertCount(1, $conditionRepository->updates);
        static::assertSame([['id' => 'script-id', 'active' => false]], $conditionRepository->updates[0]);
    }

    public function testPersistHydratesConstraintsForBoolField(): void
    {
        $result = $this->getConstraintsForField(BoolField::fromArray(['name' => 'my-field']));

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(1, $result['my-field']);
        static::assertInstanceOf(Type::class, $result['my-field'][0]);
        static::assertSame('bool', $result['my-field'][0]->type);
    }

    public function testPersistHydratesConstraintsForFloatField(): void
    {
        $result = $this->getConstraintsForField(FloatField::fromArray(['name' => 'my-field']));

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(1, $result['my-field']);
        static::assertInstanceOf(Type::class, $result['my-field'][0]);
        static::assertSame('numeric', $result['my-field'][0]->type);
    }

    public function testPersistHydratesConstraintsForIntField(): void
    {
        $result = $this->getConstraintsForField(IntField::fromArray(['name' => 'my-field']));

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(1, $result['my-field']);
        static::assertInstanceOf(Type::class, $result['my-field'][0]);
        static::assertSame('int', $result['my-field'][0]->type);
    }

    public function testPersistHydratesConstraintsForPriceField(): void
    {
        $result = $this->getConstraintsForField(PriceField::fromArray(['name' => 'my-field']));

        static::assertArrayHasKey('my-field', $result);
        static::assertSame([], $result['my-field']);
    }

    public function testPersistHydratesConstraintsForMultiEntitySelectField(): void
    {
        $result = $this->getConstraintsForField(MultiEntitySelectField::fromArray(['name' => 'my-field', 'entity' => 'product']));

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(1, $result['my-field']);
        static::assertInstanceOf(ArrayOfUuid::class, $result['my-field'][0]);
    }

    public function testPersistHydratesConstraintsForSingleEntitySelectField(): void
    {
        $result = $this->getConstraintsForField(SingleEntitySelectField::fromArray(['name' => 'my-field', 'entity' => 'product']));

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(1, $result['my-field']);
        static::assertInstanceOf(Uuid::class, $result['my-field'][0]);
    }

    public function testPersistHydratesConstraintsForMediaSelectionField(): void
    {
        $result = $this->getConstraintsForField(MediaSelectionField::fromArray(['name' => 'my-field']));

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(1, $result['my-field']);
        static::assertInstanceOf(Uuid::class, $result['my-field'][0]);
    }

    public function testPersistHydratesConstraintsForSingleSelectField(): void
    {
        $field = SingleSelectField::fromArray(['name' => 'my-field', 'options' => ['opt1' => 'Option 1', 'opt2' => 'Option 2']]);
        $result = $this->getConstraintsForField($field);

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(1, $result['my-field']);
        static::assertInstanceOf(Choice::class, $result['my-field'][0]);
        static::assertSame(['opt1', 'opt2'], $result['my-field'][0]->choices);
    }

    public function testPersistHydratesConstraintsForMultiSelectField(): void
    {
        $field = MultiSelectField::fromArray(['name' => 'my-field', 'options' => ['opt1' => 'Option 1', 'opt2' => 'Option 2']]);
        $result = $this->getConstraintsForField($field);

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(1, $result['my-field']);
        static::assertInstanceOf(All::class, $result['my-field'][0]);
        $allConstraint = $result['my-field'][0];
        static::assertIsArray($allConstraint->constraints);
        static::assertCount(1, $allConstraint->constraints);
        static::assertInstanceOf(Choice::class, $allConstraint->constraints[0]);
        static::assertSame(['opt1', 'opt2'], $allConstraint->constraints[0]->choices);
    }

    public function testPersistHydratesConstraintsForDefaultStringField(): void
    {
        $result = $this->getConstraintsForField(TextField::fromArray(['name' => 'my-field']));

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(1, $result['my-field']);
        static::assertInstanceOf(Type::class, $result['my-field'][0]);
        static::assertSame('string', $result['my-field'][0]->type);
    }

    public function testPersistHydratesConstraintsAddsNotBlankForRequiredField(): void
    {
        $result = $this->getConstraintsForField(BoolField::fromArray(['name' => 'my-field', 'required' => true]));

        static::assertArrayHasKey('my-field', $result);
        static::assertCount(2, $result['my-field']);
        static::assertInstanceOf(NotBlank::class, $result['my-field'][0]);
        static::assertInstanceOf(Type::class, $result['my-field'][1]);
        static::assertSame('bool', $result['my-field'][1]->type);
    }

    /**
     * @return array<string, list<mixed>>
     */
    private function getConstraintsForField(CustomFieldType $field): array
    {
        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$this->buildApp(new AppScriptConditionCollection())])]);

        /** @var StaticEntityRepository<AppScriptConditionCollection> $conditionRepository */
        $conditionRepository = new StaticEntityRepository([]);

        $this->scriptReader->method('getScriptContent')->willReturn('script');

        $condition = RuleCondition::fromArray([
            'identifier' => 'test-condition',
            'name' => ['en-GB' => 'Test Condition'],
            'script' => 'test.twig',
            'constraints' => [$field],
        ]);

        $persister = $this->buildPersister($appRepository, $conditionRepository);
        $persister->persist($this->buildLifecycleContext($this->createManifest([$condition])));

        /** @phpstan-ignore shopware.unserializeUsage */
        return unserialize($conditionRepository->upserts[0][0]['constraints']);
    }

    /**
     * @param StaticEntityRepository<AppCollection> $appRepository
     * @param StaticEntityRepository<AppScriptConditionCollection> $conditionRepository
     */
    private function buildPersister(
        StaticEntityRepository $appRepository,
        StaticEntityRepository $conditionRepository,
    ): RuleConditionPersister {
        return new RuleConditionPersister(
            $this->scriptReader,
            $conditionRepository,
            $appRepository,
            $this->eventDispatcher,
        );
    }

    private function buildApp(AppScriptConditionCollection $scriptConditions, string $appId = 'app-id'): AppEntity
    {
        $app = new AppEntity();
        $app->setId($appId);
        $app->setActive(true);
        $app->setScriptConditions($scriptConditions);

        return $app;
    }

    private function buildLifecycleContext(Manifest $manifest, string $appId = 'app-id'): AppLifecycleContext
    {
        $app = new AppEntity();
        $app->setId($appId);
        $app->setActive(true);

        return new AppLifecycleContext(
            manifest: $manifest,
            app: $app,
            context: Context::createDefaultContext(),
            appFilesystem: new StaticFilesystem(),
            defaultLocale: 'en-GB',
            isInstall: true,
        );
    }

    /**
     * @param list<RuleCondition> $conditions
     */
    private function createManifest(array $conditions): Manifest
    {
        $manifest = $this->createMock(Manifest::class);

        $domDocument = new \DOMDocument();
        $domElement = $domDocument->createElement('root');

        foreach ([
            'name' => 'TestApp',
            'label' => 'Test Label',
            'author' => 'Test Author',
            'copyright' => '© Test',
            'license' => 'MIT',
            'version' => '1.0.0',
        ] as $tag => $value) {
            $domElement->appendChild($domDocument->createElement($tag, $value));
        }

        $manifest->method('getMetadata')->willReturn(Metadata::fromXml($domElement));
        $manifest->method('getRuleConditions')->willReturn(
            $conditions !== [] ? RuleConditions::fromArray(['ruleConditions' => $conditions]) : null
        );

        return $manifest;
    }
}
