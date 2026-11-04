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
use Shopware\Core\System\Language\LanguageCollection;

/**
 * @experimental stableVersion:v6.8.0
 */
#[Package('framework')]
#[McpResource(
    uri: 'shopware://languages',
    name: 'shopware-languages',
    description: 'All configured languages with locale codes.'
)]
class LanguageListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<LanguageCollection> $languageRepository
     */
    public function __construct(
        private readonly EntityRepository $languageRepository,
    ) {
    }

    public function __invoke(?RequestContext $requestContext = null): ReadResourceResult
    {
        $criteria = new Criteria();
        $criteria->addAssociation('locale');

        $result = $this->languageRepository->search($criteria, Context::createDefaultContext());

        $languages = [];
        foreach ($result->getEntities() as $language) {
            $languages[] = [
                'id' => $language->getId(),
                'name' => $language->getName(),
                'localeCode' => $language->getLocale()?->getCode(),
            ];
        }

        return McpResourceCacheHint::read([
            'uri' => 'shopware://languages',
            'mimeType' => 'application/json',
            'text' => Json::encode($languages),
        ], $requestContext, McpResourceCacheHint::SHOP_CONFIGURATION_TTL_MS);
    }
}
