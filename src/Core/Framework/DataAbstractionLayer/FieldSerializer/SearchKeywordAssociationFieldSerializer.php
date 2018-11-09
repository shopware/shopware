<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;

class SearchKeywordAssociationFieldSerializer extends OneToManyAssociationFieldSerializer
{
    public function getFieldClass(): string
    {
        return SearchKeywordAssociationField::class;
    }
}
