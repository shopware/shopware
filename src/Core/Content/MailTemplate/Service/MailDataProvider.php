<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\MailTemplateEntity;
use Shopware\Core\Content\MailTemplate\MailTemplateException;
use Shopware\Core\Content\Shared\MailFlow\DataProvider\MailFlowDataProviderInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
class MailDataProvider
{
    /**
     * @var array<string, MailFlowDataProviderInterface<Entity>>
     */
    private array $dataProviders;

    /**
     * @param iterable<string, MailFlowDataProviderInterface<Entity>> $dataProviders
     */
    public function __construct(
        iterable $dataProviders,
    ) {
        $this->dataProviders = $dataProviders instanceof \Traversable ? iterator_to_array($dataProviders) : $dataProviders;
    }

    /**
     * @param array<string,string> $entityMapping
     * @param array<string,mixed> $injectedTemplateData
     *
     * @return array<string, mixed>
     */
    public function getTemplateData(
        MailTemplateEntity $mailTemplate,
        array $entityMapping,
        Context $context,
        array $injectedTemplateData = [],
    ): array {
        $availableEntities = $mailTemplate->getMailTemplateType()?->getAvailableEntities() ?? [];

        // Filter entities array so only those are left which are in the mail template's available entities list
        $entities = array_intersect_key($entityMapping, $availableEntities);

        $templateData = [];

        foreach ($entities as $key => $entityId) {
            $entityName = $availableEntities[$key];

            $dataProvider = $this->dataProviders[$entityName] ?? throw MailTemplateException::missingDataProvider($entityName);

            $data = $dataProvider->getData($entityId, $context);

            $templateData = array_merge(
                $templateData,
                [$key => $data]
            );
        }

        return \array_merge($templateData, $injectedTemplateData);
    }
}
