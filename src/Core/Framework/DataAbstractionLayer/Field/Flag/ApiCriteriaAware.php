<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Log\Package;

/**
 * Allows to bypass the ApiAware flag.
 *
 * Right now a special case for price fields because the raw prices should not be exposed but
 * the field accessor builder of this fields handles the price calculation within sql
 */
#[Package('core')]
class ApiCriteriaAware extends Flag
{
    public function parse(): \Generator
    {
        yield 'api_criteria_aware' => true;
    }
}
