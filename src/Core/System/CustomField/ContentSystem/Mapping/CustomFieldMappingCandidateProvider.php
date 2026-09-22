<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\ContentSystem\Mapping;

use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldEntity;
use Shopware\Core\System\CustomField\CustomFieldTypes;

/**
 * Offers the active, Store-API-visible custom fields assigned to a layout's root entity.
 *
 * @internal
 */
#[Package('framework')]
class CustomFieldMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    /**
     * @param EntityRepository<CustomFieldCollection> $customFieldRepository
     */
    public function __construct(private readonly EntityRepository $customFieldRepository)
    {
    }

    public function supports(string $rootSource): bool
    {
        return true;
    }

    public function provide(string $rootSource): array
    {
        $criteria = (new Criteria())
            ->addAssociation('customFieldSet.relations')
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('storeApiAware', true))
            ->addFilter(new EqualsFilter('customFieldSet.active', true))
            ->addFilter(new EqualsFilter('customFieldSet.relations.entityName', $rootSource))
            ->addSorting(new FieldSorting('name'));

        $candidates = [];

        foreach ($this->customFieldRepository->search($criteria, Context::createDefaultContext())->getEntities() as $customField) {
            $candidate = $this->candidate($rootSource, $customField);

            if ($candidate !== null) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    private function candidate(string $rootSource, CustomFieldEntity $customField): ?MappingCandidate
    {
        if ($customField->getName() === '' || str_contains($customField->getName(), '.')) {
            return null;
        }

        $config = $customField->getConfig() ?? [];
        $valueType = $this->valueType($customField->getType(), $config);

        if ($valueType === null) {
            return null;
        }

        return new MappingCandidate(
            path: $rootSource . '.customFields.' . $customField->getName(),
            label: $customField->getName(),
            description: '',
            group: 'customFields',
            valueType: $valueType,
            labelTranslations: $this->translations($config['label'] ?? null),
            descriptionTranslations: $this->translations($config['helpText'] ?? null),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function valueType(string $type, array $config): ?string
    {
        if ($type === CustomFieldTypes::SELECT) {
            return ($config['componentName'] ?? null) === 'sw-single-select' ? 'string' : null;
        }

        if ($type === CustomFieldTypes::TEXT && ($config['customFieldType'] ?? null) === 'media') {
            return null;
        }

        return match ($type) {
            CustomFieldTypes::TEXT,
            CustomFieldTypes::HTML,
            CustomFieldTypes::COLORPICKER => 'string',
            CustomFieldTypes::INT => 'integer',
            CustomFieldTypes::FLOAT,
            CustomFieldTypes::NUMBER => 'number',
            CustomFieldTypes::BOOL,
            CustomFieldTypes::CHECKBOX,
            CustomFieldTypes::SWITCH => 'boolean',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function translations(mixed $translations): array
    {
        if (!\is_array($translations)) {
            return [];
        }

        $normalized = [];

        foreach ($translations as $locale => $translation) {
            if (!\is_string($locale) || !\is_string($translation) || $translation === '') {
                continue;
            }

            $normalized[$locale] = $translation;
        }

        return $normalized;
    }
}
