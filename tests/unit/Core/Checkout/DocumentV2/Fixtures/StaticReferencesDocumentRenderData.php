<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DocumentV2\Fixtures;

use Shopware\Core\Checkout\DocumentV2\Provider\RenderData\ReferencesDocument;
use Shopware\Core\Checkout\DocumentV2\Struct\AbstractRenderData;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('after-sales')]
final readonly class StaticReferencesDocumentRenderData extends AbstractRenderData implements ReferencesDocument
{
    public function __construct(
        private string $referencedDocumentId,
    ) {
    }

    public function getReferencedDocumentId(): string
    {
        return $this->referencedDocumentId;
    }
}
