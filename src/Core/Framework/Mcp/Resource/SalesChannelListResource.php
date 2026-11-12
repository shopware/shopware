<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Schema\Result\ReadResourceResult;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Json;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopware://sales-channels',
    name: 'shopware-sales-channels',
    description: 'All sales channels with their IDs, names, types, and domains.'
)]
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

    public function __invoke(?RequestContext $requestContext = null): ReadResourceResult
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

        return McpResourceCacheHint::read([
            'uri' => 'shopware://sales-channels',
            'mimeType' => 'application/json',
            'text' => Json::encode($channels),
        ], $requestContext, McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS);
    }
}
