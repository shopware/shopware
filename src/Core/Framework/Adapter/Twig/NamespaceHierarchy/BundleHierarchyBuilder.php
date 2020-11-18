<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig\NamespaceHierarchy;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Symfony\Component\HttpKernel\KernelInterface;

class BundleHierarchyBuilder implements TemplateNamespaceHierarchyBuilderInterface
{
    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var EntityRepositoryInterface
     */
    private $appRepository;

    public function __construct(KernelInterface $kernel, EntityRepositoryInterface $appRepository)
    {
        $this->kernel = $kernel;
        $this->appRepository = $appRepository;
    }

    public function buildNamespaceHierarchy(array $namespaceHierarchy): array
    {
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $bundlePath = $bundle->getPath();

            $directory = $bundlePath . '/Resources/views';

            if (!file_exists($directory)) {
                continue;
            }

            array_unshift($namespaceHierarchy, $bundle->getName());

            $namespaceHierarchy = array_values(array_unique($namespaceHierarchy));
        }

        return array_unique(array_merge($this->getAppTemplateNamespaces(), $namespaceHierarchy));
    }

    /**
     * @return string[]
     */
    private function getAppTemplateNamespaces(): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new NotFilter(
            MultiFilter::CONNECTION_AND,
            [new EqualsFilter('app.templates.id', null)]
        ));
        $criteria->addAggregation(new TermsAggregation('appNames', 'app.name'));

        /** @var TermsResult $appNames */
        $appNames = $this->appRepository->aggregate($criteria, Context::createDefaultContext())->get('appNames');

        return $appNames->getKeys();
    }
}
