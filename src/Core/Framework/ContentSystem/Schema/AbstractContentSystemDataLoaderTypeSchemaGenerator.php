<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Schema;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
abstract class AbstractContentSystemDataLoaderTypeSchemaGenerator
{
    /**
     * @return array{sources: array<string, array{types: list<array{className: string, genericParameters: list<string>}>}>}
     */
    abstract public function getSchema(): array;
}
