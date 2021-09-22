<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;

class AppTemplateIterator implements \IteratorAggregate
{
    /**
     * @var \IteratorAggregate
     */
    private $templateIterator;

    /**
     * @var EntityRepositoryInterface
     */
    private $templateRepository;

    public function __construct(\IteratorAggregate $templateIterator, EntityRepositoryInterface $templateRepository)
    {
        $this->templateIterator = $templateIterator;
        $this->templateRepository = $templateRepository;
    }

    public function getIterator(): \Traversable
    {
        yield from $this->templateIterator;

        yield from $this->getDatabaseTemplatePaths();
    }

    /**
     * @return string[]
     */
    private function getDatabaseTemplatePaths(): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addAggregation(
            new TermsAggregation('path-names', 'path')
        );

        /** @var TermsResult $pathNames */
        $pathNames = $this->templateRepository->aggregate(
            $criteria,
            Context::createDefaultContext()
        )->get('path-names');

        return $pathNames->getKeys();
    }
}
