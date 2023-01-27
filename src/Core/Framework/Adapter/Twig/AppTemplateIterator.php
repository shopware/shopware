<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class AppTemplateIterator implements \IteratorAggregate
{
    /**
     * @internal
     */
    public function __construct(
        private readonly \IteratorAggregate $templateIterator,
        private readonly EntityRepository $templateRepository
    ) {
    }

    public function getIterator(): \Traversable
    {
        yield from $this->templateIterator;

        yield from $this->getDatabaseTemplatePaths();
    }

    /**
     * @return array<string>
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
