<?php declare(strict_types=1);

namespace Shopware\Core\System\CustomField\ContentSystem\Mapping;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\LandingPage\LandingPageEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolution;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceResolutionContext;
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
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

/**
 * Offers the active, Store-API-visible custom fields assigned to a layout's root entity.
 *
 * @internal
 */
#[Package('framework')]
class CustomFieldMappingCandidateProvider extends AbstractMappingCandidateProvider
{
    private const CACHE_TAG = 'custom_field_mapping_candidates';

    private const SEARCH_BATCH_SIZE = 250;

    private const ROOT_SOURCES = ['category', 'landing_page', 'product'];

    /**
     * @param EntityRepository<CustomFieldCollection> $customFieldRepository
     */
    public function __construct(
        private readonly EntityRepository $customFieldRepository,
        private readonly TagAwareCacheInterface $cache,
        private readonly LoggerInterface $logger = new NullLogger(),
    ) {
    }

    public function supports(string $rootSource): bool
    {
        return \in_array($rootSource, self::ROOT_SOURCES, true);
    }

    public function provide(string $rootSource): array
    {
        if (!$this->supports($rootSource)) {
            return [];
        }

        return $this->cache->get(
            'content_system.custom_field_mapping_candidates.' . $rootSource,
            function (ItemInterface $item) use ($rootSource): array {
                $item->tag(self::CACHE_TAG);

                return $this->loadCandidates($rootSource);
            }
        );
    }

    public function resolveRootSource(MappingSourceReference $source, MappingSourceResolutionContext $context): MappingSourceResolution
    {
        if ($source->type !== 'root' || !$this->supports($source->id) || !str_starts_with((string) $source->path, 'customFields.')) {
            return MappingSourceResolution::noValue();
        }

        $entity = $context->rootValues[$source->id] ?? null;
        if (!$entity instanceof ProductEntity && !$entity instanceof CategoryEntity && !$entity instanceof LandingPageEntity) {
            return MappingSourceResolution::noValue();
        }

        $fieldName = substr((string) $source->path, \strlen('customFields.'));
        $customFields = $entity->getCustomFields() ?? [];

        return \array_key_exists($fieldName, $customFields)
            ? MappingSourceResolution::resolved($customFields[$fieldName])
            : MappingSourceResolution::noValue();
    }

    /**
     * @return list<MappingCandidate>
     */
    private function loadCandidates(string $rootSource): array
    {
        /** @var array<string, list<array{candidate: MappingCandidate, id: string}>> $candidateRows */
        $candidateRows = [];
        $offset = 0;

        do {
            $criteria = (new Criteria())
                ->addAssociation('customFieldSet.relations')
                ->addFilter(new EqualsFilter('active', true))
                ->addFilter(new EqualsFilter('storeApiAware', true))
                ->addFilter(new EqualsFilter('customFieldSet.active', true))
                ->addFilter(new EqualsFilter('customFieldSet.relations.entityName', $rootSource))
                ->addSorting(new FieldSorting('name'))
                ->setLimit(self::SEARCH_BATCH_SIZE)
                ->setOffset($offset);
            $fields = $this->customFieldRepository->search($criteria, Context::createDefaultContext())->getEntities();

            foreach ($fields as $customField) {
                $candidate = $this->candidate($rootSource, $customField);

                if ($candidate !== null) {
                    $candidateRows[$candidate->source->displayName()][] = [
                        'candidate' => $candidate,
                        'id' => $customField->getId(),
                    ];
                }
            }

            $fieldCount = $fields->count();
            $offset += $fieldCount;
        } while ($fieldCount === self::SEARCH_BATCH_SIZE);

        $candidates = [];
        foreach ($candidateRows as $candidateKey => $rows) {
            if (\count($rows) > 1) {
                $this->logger->warning('Ambiguous custom-field mapping candidates were excluded from the catalogue.', [
                    'rootSource' => $rootSource,
                    'candidate' => $candidateKey,
                    'customFieldIds' => array_column($rows, 'id'),
                ]);

                continue;
            }

            $candidates[] = $rows[0]['candidate'];
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

        $cacheTags = [CustomFieldMappingCacheTags::candidate($customField->getId())];
        $customFieldSetId = $customField->getCustomFieldSetId();
        if ($customFieldSetId !== null) {
            $cacheTags[] = CustomFieldMappingCacheTags::set($customFieldSetId);
        }

        return new MappingCandidate(
            path: $rootSource . '.customFields.' . $customField->getName(),
            label: $customField->getName(),
            description: '',
            group: 'customFields',
            valueType: $valueType,
            labelTranslations: $this->translations($config['label'] ?? null),
            descriptionTranslations: $this->translations($config['helpText'] ?? null),
            cacheTags: $cacheTags,
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
