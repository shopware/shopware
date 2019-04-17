<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Listing\Subscriber;

use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Search\Util\KeywordSearchTermInterpreterInterface;
use Shopware\Storefront\Event\ListingEvents;
use Shopware\Storefront\Pagelet\Listing\ListingPageletCriteriaCreatedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SearchTermSubscriber implements EventSubscriberInterface
{
    public const TERM_PARAMETER = 'search';

    /**
     * @var KeywordSearchTermInterpreterInterface
     */
    private $interpreter;

    public function __construct(KeywordSearchTermInterpreterInterface $interpreter)
    {
        $this->interpreter = $interpreter;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ListingEvents::LISTING_PAGELET_CRITERIA_CREATED_EVENT => 'buildCriteria',
        ];
    }

    public function buildCriteria(ListingPageletCriteriaCreatedEvent $event): void
    {
        $request = $event->getRequest();

        $term = trim((string) $request->query->get(self::TERM_PARAMETER));

        if (empty($term)) {
            return;
        }

        $criteria = $event->getCriteria();

        $pattern = $this->interpreter->interpret($term, ProductDefinition::getEntityName(), $event->getContext());

        $keywordField = 'product.searchKeywords.keyword';
        $rankingField = 'product.searchKeywords.ranking';
        $languageField = 'product.searchKeywords.languageId';

        foreach ($pattern->getTerms() as $searchTerm) {
            $criteria->addQuery(
                new ScoreQuery(
                    new EqualsFilter($keywordField, $searchTerm->getTerm()),
                    $searchTerm->getScore(),
                    $rankingField
                )
            );
        }

        $criteria->addQuery(
            new ScoreQuery(
                new ContainsFilter($keywordField, $pattern->getOriginal()->getTerm()),
                $pattern->getOriginal()->getScore(),
                $rankingField
            )
        );

        $criteria->addFilter(new EqualsAnyFilter($keywordField, array_values($pattern->getAllTerms())));
        $criteria->addFilter(new EqualsFilter($languageField, $event->getContext()->getLanguageId()));
    }
}
