<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Twig;

use Shopware\Core\Framework\Adapter\Database\MySQLFactory;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @deprecated tag:v6.8.0 - reason:becomes-internal - Will be internal in v6.8.0
 */
#[Package('framework')]
class AppTemplateIterator implements TemplatePathIteratorInterface
{
    /**
     * @internal
     *
     * @param EntityRepository<TemplateCollection> $templateRepository
     */
    public function __construct(
        private readonly TemplatePathIteratorInterface $templateIterator,
        private readonly EntityRepository $templateRepository
    ) {
    }

    public function getIterator(): \Traversable
    {
        yield from $this->templateIterator;

        yield from $this->getDatabaseTemplatePaths();
    }

    /**
     * @return iterable<string>
     */
    public function getTemplatePathsForSubPath(string $subPath, bool $includeDotFiles = false): iterable
    {
        $subPath = trim($subPath, '/');
        if ($subPath === '') {
            return;
        }

        yield from $this->templateIterator->getTemplatePathsForSubPath($subPath, $includeDotFiles);

        foreach ($this->getDatabaseTemplatePaths($subPath) as $templatePath) {
            if ($includeDotFiles || !str_contains('/' . mb_substr($templatePath, mb_strlen($subPath) + 1), '/.')) {
                yield $templatePath;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function getDatabaseTemplatePaths(?string $subPath = null): array
    {
        if (MySQLFactory::hasNoDatabaseAvailable()) {
            return [];
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('active', true));

        if ($subPath !== null) {
            $criteria->addFilter(new PrefixFilter('path', $subPath . '/'));
        }

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
