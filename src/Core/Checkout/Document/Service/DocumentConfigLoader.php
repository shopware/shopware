<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Service;

use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigCollection;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfig\DocumentBaseConfigEntity;
use Shopware\Core\Checkout\Document\DocumentConfiguration;
use Shopware\Core\Checkout\Document\DocumentConfigurationFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
final class DocumentConfigLoader implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, array<string, DocumentConfiguration>>
     */
    private array $configs = [];

    private EntityRepository $documentConfigRepository;

    /**
     * @internal
     */
    public function __construct(EntityRepository $documentConfigRepository)
    {
        $this->documentConfigRepository = $documentConfigRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'document_base_config.written' => 'reset',
        ];
    }

    public function load(string $documentType, string $salesChannelId, Context $context): DocumentConfiguration
    {
        if (!empty($this->configs[$documentType][$salesChannelId])) {
            return $this->configs[$documentType][$salesChannelId];
        }

        $criteria = new Criteria();

        $criteria->addFilter(new EqualsFilter('documentType.technicalName', $documentType));
        $criteria->addAssociation('logo');
        $criteria->getAssociation('salesChannels')->addFilter(new EqualsFilter('salesChannelId', $salesChannelId));

        /** @var DocumentBaseConfigCollection $documentConfigs */
        $documentConfigs = $this->documentConfigRepository->search($criteria, $context)->getEntities();

        $globalConfig = $documentConfigs->filterByProperty('global', true)->first();

        $salesChannelConfig = $documentConfigs->filter(function (DocumentBaseConfigEntity $config) {
            return $config->getSalesChannels()->count() > 0;
        })->first();

        $config = DocumentConfigurationFactory::createConfiguration([], $globalConfig, $salesChannelConfig);

        $this->configs[$documentType] = $this->configs[$documentType] ?? [];

        return $this->configs[$documentType][$salesChannelId] = $config;
    }

    public function reset(): void
    {
        $this->configs = [];
    }
}
