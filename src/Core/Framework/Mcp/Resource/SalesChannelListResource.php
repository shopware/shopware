<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpResource(uri: 'shopware://sales-channels', name: 'shopware-sales-channels', description: 'All sales channels with their IDs, names, types, and domains.')]
#[Package('framework')]
class SalesChannelListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelCollection> $salesChannelRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelRepository,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $criteria = new Criteria();
        $criteria->addAssociation('domains');
        $criteria->addAssociation('type');

        $result = $this->salesChannelRepository->search($criteria, Context::createDefaultContext());

        $channels = [];
        foreach ($result->getEntities() as $channel) {
            $domains = [];
            foreach ($channel->getDomains() ?? [] as $domain) {
                $domains[] = [
                    'url' => $domain->getUrl(),
                    'languageId' => $domain->getLanguageId(),
                ];
            }

            $channels[] = [
                'id' => $channel->getId(),
                'name' => $channel->getName(),
                'type' => $channel->getType()?->getName(),
                'active' => $channel->getActive(),
                'domains' => $domains,
            ];
        }

        return [
            'uri' => 'shopware://sales-channels',
            'mimeType' => 'application/json',
            'text' => json_encode($channels, \JSON_THROW_ON_ERROR),
        ];
    }
}
