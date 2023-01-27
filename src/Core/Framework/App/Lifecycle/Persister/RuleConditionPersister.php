<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Lifecycle\Persister;

use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionCollection;
use Shopware\Core\Framework\App\Aggregate\AppScriptCondition\AppScriptConditionEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Lifecycle\ScriptFileReaderInterface;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\BoolField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\CustomFieldType;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\FloatField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\IntField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\MediaSelectionField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\MultiEntitySelectField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\MultiSelectField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\PriceField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\SingleEntitySelectField;
use Shopware\Core\Framework\App\Manifest\Xml\CustomFieldTypes\SingleSelectField;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Shopware\Core\Framework\Validation\Constraint\Uuid;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[Package('core')]
class RuleConditionPersister
{
    private const CONDITION_SCRIPT_DIR = '/rule-conditions/';

    public function __construct(
        private readonly ScriptFileReaderInterface $scriptReader,
        private readonly EntityRepository $appScriptConditionRepository,
        private readonly EntityRepository $appRepository
    ) {
    }

    public function updateConditions(Manifest $manifest, string $appId, string $defaultLocale, Context $context): void
    {
        $app = $this->getAppWithExistingConditions($appId, $context);

        /** @var AppScriptConditionCollection $existingRuleConditions */
        $existingRuleConditions = $app->getScriptConditions();

        $ruleConditions = $manifest->getRuleConditions();
        $ruleConditions = $ruleConditions !== null ? $ruleConditions->getRuleConditions() : [];

        $upserts = [];

        foreach ($ruleConditions as $ruleCondition) {
            $payload = $ruleCondition->toArray($defaultLocale);
            $payload['identifier'] = sprintf('app\\%s_%s', $manifest->getMetadata()->getName(), $ruleCondition->getIdentifier());
            $payload['script'] = $this->scriptReader->getScriptContent(
                self::CONDITION_SCRIPT_DIR . $ruleCondition->getScript(),
                $app->getPath()
            );
            $payload['appId'] = $appId;
            $payload['active'] = $app->isActive();
            $payload['constraints'] = $this->hydrateConstraints($payload['constraints']);

            /** @var AppScriptConditionEntity|null $existing */
            $existing = $existingRuleConditions->filterByProperty('identifier', $payload['identifier'])->first();

            if ($existing) {
                $existingRuleConditions->remove($existing->getId());
                $payload['id'] = $existing->getId();
            }

            $upserts[] = $payload;
        }

        if (!empty($upserts)) {
            $context->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($upserts): void {
                $this->appScriptConditionRepository->upsert($upserts, $context);
            });
        }

        $this->deleteConditionScripts($existingRuleConditions, $context);
    }

    public function activateConditionScripts(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', false));

        /** @var array<string> $scripts */
        $scripts = $this->appScriptConditionRepository->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(fn (string $id) => ['id' => $id, 'active' => true], $scripts);

        $this->appScriptConditionRepository->update($updateSet, $context);
    }

    public function deactivateConditionScripts(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var array<string> $scripts */
        $scripts = $this->appScriptConditionRepository->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(fn (string $id) => ['id' => $id, 'active' => false], $scripts);

        $this->appScriptConditionRepository->update($updateSet, $context);
    }

    private function getAppWithExistingConditions(string $appId, Context $context): AppEntity
    {
        $criteria = new Criteria([$appId]);
        $criteria->addAssociation('scriptConditions');

        /** @var AppEntity $app */
        $app = $this->appRepository->search($criteria, $context)->first();

        return $app;
    }

    private function deleteConditionScripts(AppScriptConditionCollection $toBeRemoved, Context $context): void
    {
        /** @var array<string> $ids */
        $ids = $toBeRemoved->getIds();

        if (!empty($ids)) {
            $ids = array_map(static fn (string $id): array => ['id' => $id], array_values($ids));

            $this->appScriptConditionRepository->delete($ids, $context);
        }
    }

    /**
     * @param CustomFieldType[] $fields
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
                $constraints[$field->getName()][] = new All([new Choice(array_keys($field->getOptions()))]);

                continue;
            }

            if ($field instanceof SingleSelectField) {
                $constraints[$field->getName()][] = new Choice(array_keys($field->getOptions()));

                continue;
            }

            $constraints[$field->getName()][] = new Type('string');
        }

        return serialize($constraints);
    }
}
