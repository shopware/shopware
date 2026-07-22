<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Provider\RenderData;

use Shopware\Core\Framework\Log\Package;

/**
 * Implemented by render data of a document that is derived from another document (credit note,
 * cancellation invoice), so the generator can persist the referenced document id.
 *
 * @internal
 */
#[Package('after-sales')]
interface ReferencesDocument
{
    public function getReferencedDocumentId(): ?string;
}
