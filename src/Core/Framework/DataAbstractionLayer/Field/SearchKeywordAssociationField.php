<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\WriteProtected;
use Shopware\Core\Framework\Search\SearchDocumentDefinition;

class SearchKeywordAssociationField extends OneToManyAssociationField
{
    public function __construct()
    {
        parent::__construct('searchKeywords', SearchDocumentDefinition::class, 'entity_id', false);
        $this->addFlags(new WriteProtected());
    }
}
