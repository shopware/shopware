<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Framework\Log\Package;

/**
 * Declares which order snapshot the generation pipeline loads for a document type.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum OrderVersionStrategy
{
    case CREATE;

    case REFERENCED;
}
