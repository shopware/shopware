<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('inventory')]
class ProductSearchBuilder implements ProductSearchBuilderInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductSearchTermInterpreterInterface $interpreter,
        private readonly LoggerInterface $logger,
        private readonly int $searchTermMaxLength,
        private readonly bool $searchKeywordIndexingEnabled = true,
    ) {
    }

    public function build(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        $search = RequestParamHelper::get($request, 'search');

        if (\is_array($search)) {
            $term = implode(' ', $search);
        } else {
            $term = (string) $search;
        }

        $term = trim($term);
        if (mb_strlen($term) > $this->searchTermMaxLength) {
            $this->logger->notice(
                'The search term "{term}" was trimmed because it exceeded the maximum length of {maxLength} characters.',
                ['term' => $term, 'maxLength' => $this->searchTermMaxLength]
            );

            $term = mb_substr($term, 0, $this->searchTermMaxLength);
        }

        if ($term === '') {
            throw ProductException::missingRequestParameter('search');
        }

        if (!$this->searchKeywordIndexingEnabled) {
            $criteria->setTerm($term);

            return;
        }

        $pattern = $this->interpreter->interpret($term, $context->getContext());

        foreach ($pattern->getTerms() as $searchTerm) {
            $criteria->addQuery(
                new ScoreQuery(
                    new EqualsFilter('product.searchKeywords.keyword', $searchTerm->getTerm()),
                    $searchTerm->getScore(),
                    'product.searchKeywords.ranking'
                )
            );
        }
        $criteria->addQuery(
            new ScoreQuery(
                new ContainsFilter('product.searchKeywords.keyword', $pattern->getOriginal()->getTerm()),
                $pattern->getOriginal()->getScore(),
                'product.searchKeywords.ranking'
            )
        );

        $minimumShouldMatch = $pattern->getMinimumShouldMatch();
        $tokenTermFilters = array_map(
            fn (array $terms): AndFilter => $this->createTokenTermsFilter($terms, $context->getLanguageId()),
            $pattern->getTokenTerms()
        );

        if ($minimumShouldMatch <= 1 || \count($tokenTermFilters) <= 1) {
            $criteria->addFilter(new AndFilter([
                new EqualsAnyFilter('product.searchKeywords.keyword', array_values($pattern->getAllTerms())),
                new EqualsFilter('product.searchKeywords.languageId', $context->getLanguageId()),
            ]));

            return;
        }

        if ($minimumShouldMatch >= \count($tokenTermFilters) || $pattern->getBooleanClause() === SearchPattern::BOOLEAN_CLAUSE_AND) {
            foreach ($tokenTermFilters as $tokenTermFilter) {
                $criteria->addFilter($tokenTermFilter);
            }

            return;
        }

        $combinations = array_map(
            static fn (array $filters): AndFilter => new AndFilter($filters),
            $this->combineFilters($tokenTermFilters, $minimumShouldMatch)
        );

        $criteria->addFilter(new OrFilter($combinations));
    }

    /**
     * @param list<string> $terms
     */
    private function createTokenTermsFilter(array $terms, string $languageId): AndFilter
    {
        return new AndFilter([
            new EqualsFilter('product.searchKeywords.languageId', $languageId),
            new EqualsAnyFilter('product.searchKeywords.keyword', $terms),
        ]);
    }

    /**
     * @param list<Filter> $filters
     *
     * @return list<list<Filter>>
     */
    private function combineFilters(array $filters, int $requiredMatches): array
    {
        if ($requiredMatches <= 0) {
            return [[]];
        }

        if ($requiredMatches > \count($filters)) {
            return [];
        }

        $combinations = [];

        foreach ($filters as $index => $filter) {
            $remainingFilters = \array_slice($filters, $index + 1);

            foreach ($this->combineFilters($remainingFilters, $requiredMatches - 1) as $combination) {
                $combinations[] = [$filter, ...$combination];
            }
        }

        return $combinations;
    }
}
