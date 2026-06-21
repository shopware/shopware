<?php declare(strict_types=1);

namespace Shopware\Storefront\ContentSystem\Validation;

use Shopware\Core\Framework\ContentSystem\Binding\BoundRootContext;
use Shopware\Core\Framework\ContentSystem\Binding\LayoutBindingEnumerator;
use Shopware\Core\Framework\ContentSystem\ContentSection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * Enumerates the Storefront header/footer bindings of a content layout. Both sections expose no root-ambient
 * context, so each binding carries an empty provided root context — a layout bound to a header must be fully
 * resolvable without page data. Reaches the Core re-check via the tagged LayoutBindingEnumerator contract with
 * no reverse Core→Storefront dependency.
 *
 * @internal
 */
#[Package('framework')]
class HeaderFooterBindingEnumerator implements LayoutBindingEnumerator
{
    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $headerRepository
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $footerRepository
     */
    public function __construct(
        private readonly EntityRepository $headerRepository,
        private readonly EntityRepository $footerRepository,
    ) {
    }

    public function enumerate(string $contentLayoutId, Context $context): array
    {
        $bindings = [];

        if ($this->hasAssignment($this->headerRepository, $contentLayoutId, $context)) {
            $bindings[] = new BoundRootContext(ContentSection::HEADER->value, []);
        }

        if ($this->hasAssignment($this->footerRepository, $contentLayoutId, $context)) {
            $bindings[] = new BoundRootContext(ContentSection::FOOTER->value, []);
        }

        return $bindings;
    }

    /**
     * @param EntityRepository<covariant EntityCollection<covariant Entity>> $repository
     */
    private function hasAssignment(EntityRepository $repository, string $contentLayoutId, Context $context): bool
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('contentLayoutId', $contentLayoutId));
        $criteria->setLimit(1);

        return $repository->searchIds($criteria, $context)->firstId() !== null;
    }
}
