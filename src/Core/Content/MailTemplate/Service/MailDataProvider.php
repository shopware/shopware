<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class MailDataProvider
{
    /**
     * @var array<string, AbstractProvider<Entity, EntityCollection<Entity>>>
     */
    private array $dataProviders;

    /**
     * @param iterable<string, AbstractProvider<Entity, EntityCollection<Entity>>> $dataProviders
     */
    public function __construct(
        iterable $dataProviders,
        private readonly JsonEntityEncoder $jsonEntityEncoder,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
    ) {
        $this->dataProviders = $dataProviders instanceof \Traversable ? iterator_to_array($dataProviders) : $dataProviders;
    }

    /**
     * @param array<string, string> $entities
     *
     * @return array<string, mixed>
     */
    public function getTemplateData(MailTemplateEntity $mailTemplate, array $entities, Context $context): array
    {
        $availableEntities = $mailTemplate->getMailTemplateType()?->getAvailableEntities() ?? [];

        // Filter entities array so only those are left which are in the mail template's available entities list
        $entities = \array_filter(
            $entities,
            function (string $entityName) use ($availableEntities) {
                return \in_array($entityName, $availableEntities, true);
            },
            \ARRAY_FILTER_USE_KEY
        );

        $templateData = [];

        foreach ($entities as $entityName => $entityId) {
            $dataProvider = $this->dataProviders[$entityName];

            $data = $dataProvider->getData($entityId, $context);

            $templateData = array_merge(
                $templateData,
                [$entityName => $data]
            );
        }

        foreach ($templateData as $key => $value) {
            if (!$value instanceof Entity || empty($value->getInternalEntityName())) {
                continue;
            }

            $definition = $this->definitionInstanceRegistry->getByEntityName(
                $value->getInternalEntityName()
            );

            $templateData[$key] = $this->jsonEntityEncoder->encode(
                new Criteria(),
                $definition,
                $value,
                '/api'
            );
        }

        return $templateData;
    }
}
