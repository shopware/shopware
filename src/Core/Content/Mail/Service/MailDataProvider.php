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
     * @param DataProvider[] $dataProviders
     */
    public function __construct(
        private readonly iterable $dataProviders,
        private readonly JsonEntityEncoder $jsonEntityEncoder,
        private readonly DefinitionInstanceRegistry $definitionInstanceRegistry,
    ) {
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

        foreach ($this->dataProviders as $dataProvider) {
            foreach ($entities as $entityName => $entityId) {
                if ($dataProvider->supports($entityName)) {
                    $templateData = array_merge(
                        $templateData,
                        [$entityName => $dataProvider->getData($entityId, $context)]
                    );
                }
            }
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
