<?php declare(strict_types=1);

namespace Shopware\Core\Content\ImportExportV2\Service;

use Shopware\Core\Content\ImportExportV2\Exception\ImportExportV2Exception;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Shopware\Core\Framework\Log\Package;

/**
 * Reuses the normal DAL request filter parser so import/export accepts the
 * same JSON filter structure as the Shopware API.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ExportFilterApplier
{
    public function __construct(private readonly DefinitionInstanceRegistry $definitionInstanceRegistry)
    {
    }

    /**
     * @param list<array<string, mixed>> $filters
     */
    public function apply(Criteria $criteria, string $entity, array $filters): void
    {
        $definition = $this->definitionInstanceRegistry->getByEntityName($entity);
        $searchException = new SearchRequestException();

        foreach ($filters as $index => $filterConfig) {
            if (!\is_array($filterConfig)) {
                throw ImportExportV2Exception::invalidExportFilter(
                    'filters[' . $index . ']',
                    'Each filter must be an object.'
                );
            }

            try {
                $criteria->addFilter(QueryStringParser::fromArray(
                    $definition,
                    $filterConfig,
                    $searchException,
                    '/filter/' . $index
                ));
            } catch (\Throwable $exception) {
                throw ImportExportV2Exception::invalidExportFilter(
                    'filters[' . $index . ']',
                    $exception->getMessage()
                );
            }
        }

        try {
            $searchException->tryToThrow();
        } catch (SearchRequestException $exception) {
            throw ImportExportV2Exception::invalidExportFilter('filters', $exception->getMessage());
        }
    }
}
