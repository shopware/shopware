<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp\Resource;

use Mcp\Capability\Attribute\McpResource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Currency\CurrencyCollection;

/**
 * @experimental stableVersion:v6.8.0 feature:MCP_SERVER
 */
#[McpResource(uri: 'shopware://currencies', name: 'shopware-currencies', description: 'All configured currencies with ISO codes, symbols, and conversion factors.')]
#[Package('framework')]
class CurrencyListResource
{
    /**
     * @internal
     *
     * @param EntityRepository<CurrencyCollection> $currencyRepository
     */
    public function __construct(
        private readonly EntityRepository $currencyRepository,
    ) {
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    public function __invoke(): array
    {
        $result = $this->currencyRepository->search(new Criteria(), Context::createDefaultContext());

        $currencies = [];
        foreach ($result->getEntities() as $currency) {
            $currencies[] = [
                'id' => $currency->getId(),
                'isoCode' => $currency->getIsoCode(),
                'symbol' => $currency->getSymbol(),
                'factor' => $currency->getFactor(),
                'name' => $currency->getName(),
            ];
        }

        return [
            'uri' => 'shopware://currencies',
            'mimeType' => 'application/json',
            'text' => json_encode($currencies, \JSON_THROW_ON_ERROR),
        ];
    }
}
