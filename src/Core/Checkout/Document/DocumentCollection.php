<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class DocumentCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return DocumentEntity::class;
    }
}
