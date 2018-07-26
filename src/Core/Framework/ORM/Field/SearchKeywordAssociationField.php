<?php
declare(strict_types=1);

namespace Shopware\Core\Framework\ORM\Field;

use Shopware\Core\Framework\ORM\Write\Flag\ReadOnly;
use Shopware\Core\Framework\Search\SearchDocumentDefinition;

class SearchKeywordAssociationField extends OneToManyAssociationField
{
    public function __construct()
    {
        parent::__construct('searchKeywords', SearchDocumentDefinition::class, 'entity_id', false);
        $this->addFlags(new ReadOnly());
    }
}
