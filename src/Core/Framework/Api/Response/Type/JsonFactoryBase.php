<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Response\Type;

use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
abstract class JsonFactoryBase implements ResponseFactoryInterface
{
    public function createRedirectResponse(EntityDefinition $definition, string $id, Request $request, Context $context): Response
    {
        $headers = [
            'Location' => $this->getEntityBaseUrl($request, $definition) . '/' . $id,
        ];

        return new Response(null, Response::HTTP_NO_CONTENT, $headers);
    }

    abstract protected function getApiBaseUrl(Request $request): string;

    protected function getEntityBaseUrl(Request $request, EntityDefinition $definition): string
    {
        $apiCase = $this->getApiBaseUrl($request);

        return $apiCase . '/' . $this->camelCaseToSnailCase($definition->getEntityName());
    }

    protected function createPaginationLinks(EntitySearchResult $searchResult, string $uri, array $parameters): array
    {
        $limit = $searchResult->getCriteria()->getLimit() ?? 0;
        $offset = $searchResult->getCriteria()->getOffset() ?? 0;

        if ($limit <= 0) {
            return [];
        }
        $pagination = [
            'first' => $this->buildPaginationUrl($uri, $parameters, $limit, 1),
        ];

        $currentPage = 1 + (int) floor($offset / $limit);
        if ($currentPage > 1) {
            $pagination['prev'] = $this->buildPaginationUrl($uri, $parameters, $limit, $currentPage - 1);
        }

        $totalCountMode = $searchResult->getCriteria()->getTotalCountMode();
        switch ($totalCountMode) {
            case Criteria::TOTAL_COUNT_MODE_NONE:
                if ($searchResult->getTotal() >= $limit) {
                    $pagination['next'] = $this->buildPaginationUrl($uri, $parameters, $limit, $currentPage + 1);
                }

                break;

            case Criteria::TOTAL_COUNT_MODE_EXACT:
                $lastPage = (int) ceil($searchResult->getTotal() / $limit);
                $lastPage = $lastPage >= 1 ? $lastPage : 1;
                $pagination['last'] = $this->buildPaginationUrl($uri, $parameters, $limit, $lastPage);

                if ($currentPage < $lastPage) {
                    $pagination['next'] = $this->buildPaginationUrl($uri, $parameters, $limit, $currentPage + 1);
                }

                break;

            case Criteria::TOTAL_COUNT_MODE_NEXT_PAGES:
                $remaining = $searchResult->getTotal();
                $maxFetchCount = $limit * 5 + 1;
                if ($remaining && $remaining > $limit) {
                    $pagination['next'] = $this->buildPaginationUrl($uri, $parameters, $limit, $currentPage + 1);
                }
                if ($remaining > 0 && $remaining < $maxFetchCount) {
                    $lastPage = $currentPage - 1 + (int) ceil($remaining / $limit);
                    $pagination['last'] = $this->buildPaginationUrl($uri, $parameters, $limit, $lastPage);
                }

                break;
        }

        return $pagination;
    }

    protected function buildPaginationUrl(string $uri, array $parameters, int $limit, int $page): string
    {
        $params = array_merge($parameters, ['limit' => $limit, 'page' => $page]);

        return $uri . '?' . http_build_query($params);
    }

    protected function getBaseUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost() . $request->getBasePath();
    }

    protected function camelCaseToSnailCase(string $input): string
    {
        $input = str_replace('_', '-', $input);

        return ltrim(mb_strtolower((string) preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }
}
