<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\AppLifecycleContext;
use Shopware\Core\Framework\App\Lifecycle\Persister\Extension\RuleConditionActivateExtension;
use Shopware\Core\Framework\App\Lifecycle\Persister\Extension\RuleConditionDeactivateExtension;
use Shopware\Core\Framework\App\Lifecycle\Persister\Extension\RuleConditionPersistExtension;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReader;
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
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class RuleConditionPersister implements PersisterInterface
{
    private const CONDITION_SCRIPT_DIR = '/rule-conditions/';

    /**
     * @param EntityRepository<AppScriptConditionCollection> $appScriptConditionRepository
     * @param EntityRepository<AppCollection> $appRepository
     */
    public function __construct(
        private readonly ScriptFileReader $scriptReader,
        private readonly EntityRepository $appScriptConditionRepository,
        private readonly EntityRepository $appRepository,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function persist(AppLifecycleContext $context): void
    {
        $this->extensions->publish(
            name: RuleConditionPersistExtension::NAME,
            extension: new RuleConditionPersistExtension($context),
            function: $this->doPersist(...)
        );
    }

    public function activateConditionScripts(string $appId, Context $context): void
    {
        $this->extensions->publish(
            name: RuleConditionActivateExtension::NAME,
            extension: new RuleConditionActivateExtension($appId, $context),
            function: $this->doActivateConditionScripts(...)
        );
    }

    public function deactivateConditionScripts(string $appId, Context $context): void
    {
        $this->extensions->publish(
            name: RuleConditionDeactivateExtension::NAME,
            extension: new RuleConditionDeactivateExtension($appId, $context),
            function: $this->doDeactivateConditionScripts(...)
        );
    }

    private function doPersist(AppLifecycleContext $context): void
    {
        $app = $this->getAppWithExistingConditions($context->app->getId(), $context->context);
        $existingRuleConditions = $app->getScriptConditions();
        \assert($existingRuleConditions !== null);

        $ruleConditions = $context->manifest->getRuleConditions();
        $ruleConditions = $ruleConditions !== null ? $ruleConditions->getRuleConditions() : [];

        $upserts = [];

        foreach ($ruleConditions as $ruleCondition) {
            $payload = $ruleCondition->toArray($context->defaultLocale);
            $payload['identifier'] = \sprintf('app\\%s_%s', $context->manifest->getMetadata()->getName(), $ruleCondition->getIdentifier());
            $payload['script'] = $this->scriptReader->getScriptContent(
                $app,
                self::CONDITION_SCRIPT_DIR . $ruleCondition->getScript(),
            );
            $payload['appId'] = $context->app->getId();
            $payload['active'] = $app->isActive();
            $payload['constraints'] = $this->hydrateConstraints($payload['constraints']);

            $existing = $existingRuleConditions->filterByProperty('identifier', $payload['identifier'])->first();

            if ($existing) {
                $existingRuleConditions->remove($existing->getId());
                $payload['id'] = $existing->getId();
            }

            $upserts[] = $payload;
        }

        if ($upserts !== []) {
            $context->context->scope(Context::SYSTEM_SCOPE, function (Context $innerContext) use ($upserts): void {
                $this->appScriptConditionRepository->upsert($upserts, $innerContext);
            });
        }

        $this->deleteConditionScripts($existingRuleConditions, $context->context);
    }

    private function doActivateConditionScripts(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', false));

        $scripts = $this->appScriptConditionRepository->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(static fn (string $id) => ['id' => $id, 'active' => true], $scripts);

        $this->appScriptConditionRepository->update($updateSet, $context);
    }

    private function doDeactivateConditionScripts(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        $scripts = $this->appScriptConditionRepository->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(static fn (string $id) => ['id' => $id, 'active' => false], $scripts);

        $this->appScriptConditionRepository->update($updateSet, $context);
    }

    private function getAppWithExistingConditions(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('scriptConditions');

        $app = $this->appRepository->search($criteria, $context)->getEntities()->first();
        \assert($app !== null);

        return $app;
    }

    private function deleteConditionScripts(AppScriptConditionCollection $toBeRemoved, Context $context): void
    {
        $ids = $toBeRemoved->getIds();

        if ($ids !== []) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->appScriptConditionRepository->delete($ids, $context);
        }
    }

    /**
     * @param list<CustomFieldType> $fields
     */
    private function hydrateConstraints(array $fields): string
    {
        $constraints = [];

        foreach ($fields as $field) {
            $constraints[$field->getName()] = [];

            if ($field->getRequired()) {
                $constraints[$field->getName()][] = new NotBlank();
            }

            if ($field instanceof PriceField) {
                continue;
            }

            if ($field instanceof BoolField) {
                $constraints[$field->getName()][] = new Type('bool');

                continue;
            }

            if ($field instanceof FloatField) {
                $constraints[$field->getName()][] = new Type('numeric');

                continue;
            }

            if ($field instanceof IntField) {
                $constraints[$field->getName()][] = new Type('int');

                continue;
            }

            if ($field instanceof MultiEntitySelectField) {
                $constraints[$field->getName()][] = new ArrayOfUuid();

                continue;
            }

            if ($field instanceof SingleEntitySelectField || $field instanceof MediaSelectionField) {
                $constraints[$field->getName()][] = new Uuid();

                continue;
            }

            if ($field instanceof MultiSelectField) {
                $constraints[$field->getName()][] = new All(constraints: new Choice(choices: array_keys($field->getOptions())));

                continue;
            }

            if ($field instanceof SingleSelectField) {
                $constraints[$field->getName()][] = new Choice(choices: array_keys($field->getOptions()));

                continue;
            }

            $constraints[$field->getName()][] = new Type('string');
        }

        return serialize($constraints);
    }
}
