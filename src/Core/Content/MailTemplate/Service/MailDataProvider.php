<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\AbstractProvider;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
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
    public function __construct(iterable $dataProviders)
    {
        $this->dataProviders = $dataProviders instanceof \Traversable ? iterator_to_array($dataProviders) : $dataProviders;
    }

    /**
     * @param array<string, string> $entities
     *
     * @return array<string, Entity|null>
     */
    public function getTemplateData(MailTemplateEntity $mailTemplate, array $entities, Context $context): array
    {
        $availableEntities = $mailTemplate->getMailTemplateType()?->getAvailableEntities() ?? [];

        // Filter entities array so only those are left which are in the mail template's available entities list
        $entities = array_intersect_key($entities, $availableEntities);

        $templateData = [];

        foreach ($entities as $key => $entityId) {
            $entityName = $availableEntities[$key];

            $dataProvider = $this->dataProviders[$entityName];

            $data = $dataProvider->getData($entityId, $context);

            $templateData = array_merge(
                $templateData,
                [$key => $data]
            );
        }

        return $templateData;
    }
}
