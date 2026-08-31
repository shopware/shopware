<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Framework\Log\Package;

/**
 * Marks a data provider whose document type references another document.
 *
 * The generation pipeline resolves the referenced document, fills
 * ProviderInput::$resolvedReference and persists the resolved reference id.
 *
 * @internal
 */
#[Package('after-sales')]
interface ReferencesDocument
{
}
