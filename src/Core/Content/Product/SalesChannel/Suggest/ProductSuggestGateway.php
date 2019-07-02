<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Suggest;

use Shopware\Core\Content\Product\Events\ProductSuggestCriteriaEvent;
use Shopware\Core\Content\Product\Events\ProductSuggestResultEvent;
use Shopware\Core\Content\Product\SearchKeyword\ProductSearchTermInterpreterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class ProductSuggestGateway implements ProductSuggestGatewayInterface
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $repository;

    /**
     * @var ProductSearchTermInterpreterInterface
     */
    private $interpreter;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        SalesChannelRepositoryInterface $repository,
        ProductSearchTermInterpreterInterface $interpreter,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->repository = $repository;
        $this->interpreter = $interpreter;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function suggest(Request $request, SalesChannelContext $salesChannelContext): EntitySearchResult
    {
        $criteria = new Criteria();

        $criteria->setLimit(50);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $term = trim((string) $request->query->get('search'));

        if (empty($term)) {
            throw new MissingRequestParameterException('search');
        }

        $pattern = $this->interpreter->interpret(
            $term,
            $salesChannelContext->getContext()
        );

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

        $criteria->addFilter(new EqualsAnyFilter('product.searchKeywords.keyword', array_values($pattern->getAllTerms())));
        $criteria->addFilter(new EqualsFilter('product.searchKeywords.languageId', $salesChannelContext->getContext()->getLanguageId()));

        $this->eventDispatcher->dispatch(
            new ProductSuggestCriteriaEvent($request, $criteria, $salesChannelContext)
        );

        $result = $this->repository->search($criteria, $salesChannelContext);

        $this->eventDispatcher->dispatch(
            new ProductSuggestResultEvent($request, $result, $salesChannelContext)
        );

        return $result;
    }
}
