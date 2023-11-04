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
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Service\ResetInterface;

#[Package('customer-order')]
final class DocumentConfigLoader implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var array<string, array<string, DocumentConfiguration>>
     */
    private array $configs = [];

    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $documentConfigRepository)
    {
    }

    /**
     * @internal
     */
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

        $salesChannelConfig = $documentConfigs->filter(fn (DocumentBaseConfigEntity $config) => $config->getSalesChannels()->count() > 0)->first();

        $config = DocumentConfigurationFactory::createConfiguration([], $globalConfig, $salesChannelConfig);

        $this->configs[$documentType] ??= [];

        return $this->configs[$documentType][$salesChannelId] = $config;
    }

    /**
     * @internal
     */
    public function reset(): void
    {
        $this->configs = [];
    }
}
