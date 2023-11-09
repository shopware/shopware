<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Framework;

use Psr\EventDispatcher\EventDispatcherInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NandFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexerLanguageCriteriaEvent;

#[Package('core')]
class ElasticsearchLanguageProvider
{
    /**
     * @internal
     */
    public function __construct(private readonly EntityRepository $languageRepository, private readonly EventDispatcherInterface $eventDispatcher)
    {
    }

    public function getLanguages(Context $context): LanguageCollection
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NandFilter([new EqualsFilter('salesChannels.id', null)]));
        $criteria->addSorting(new FieldSorting('id'));

        $this->eventDispatcher->dispatch(new ElasticsearchIndexerLanguageCriteriaEvent($criteria, $context));

        /** @var LanguageCollection $languages */
        $languages = $this->languageRepository
            ->search($criteria, $context)
            ->getEntities();

        return $languages;
    }
}
