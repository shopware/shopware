<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpResource(uri: 'shopware://languages', name: 'shopware-languages', description: 'All configured languages with locale codes.')]
#[Package('framework')]
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

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
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

        return [
            'uri' => 'shopware://languages',
            'mimeType' => 'application/json',
            'text' => json_encode($languages, \JSON_THROW_ON_ERROR),
        ];
    }
}
