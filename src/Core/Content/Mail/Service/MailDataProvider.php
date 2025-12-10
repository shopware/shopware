<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Content\Mail\DataProvider\DataProvider;
use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class MailDataProvider
{
    /**
     * @var array<string, DataProvider> $dataProviders
     */
    private array $dataProviders;

    /**
     * @param iterable<string, DataProvider> $dataProviders
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
        // TODO validate $entities keys against available entities in mail template type?

        $templateData = [];

        foreach ($entities as $entityName => $entityId) {
            $dataProvider = $this->dataProviders[$entityName];

            $data = $dataProvider->getData($entityId, $context);

            if ($data === null) {
                // TODO: check how flow handles it, it just returns null for missing entities, does that error?
            }

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
