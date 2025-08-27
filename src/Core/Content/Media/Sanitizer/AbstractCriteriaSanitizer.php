<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Sanitizer;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
abstract class AbstractCriteriaSanitizer
{
    abstract public function shouldSanitizeField(string $fieldPath): bool;

    abstract public function shouldSanitizeValue(mixed $value): bool;

    abstract public function getFilterReplacement(): Filter;
}
