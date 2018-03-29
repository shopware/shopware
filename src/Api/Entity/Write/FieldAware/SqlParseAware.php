<?php declare(strict_types=1);

namespace Shopware\Api\Entity\Write\FieldAware;

use Shopware\Context\Struct\ApplicationContext;

/**
 * Allows to parse the field access by your own. Helpful if the field contains json or other nested data
 * which related to the provided ApplicationContext.
 */
interface SqlParseAware
{
    public function parse(string $selection, ApplicationContext $context): string;
}
