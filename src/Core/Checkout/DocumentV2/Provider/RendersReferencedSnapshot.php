<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider;

use Shopware\Core\Framework\Log\Package;

/**
 * Marks a data provider whose document type renders the order snapshot captured by the
 * referenced document, e.g. the cancellation invoice cancelling the amounts its invoice billed.
 *
 * The generation pipeline loads the order at the referenced document's snapshot instead of
 * creating a new version, in preview too.
 *
 * @internal
 */
#[Package('after-sales')]
interface RendersReferencedSnapshot extends ReferencesDocument
{
}
