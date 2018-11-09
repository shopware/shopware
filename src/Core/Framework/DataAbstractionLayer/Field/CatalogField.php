<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field;

use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;

class CatalogField extends FkField
{
    public function __construct()
    {
        parent::__construct('catalog_id', 'catalogId', CatalogDefinition::class);

        $this->setFlags(new Required());
    }

    public function getExtractPriority(): int
    {
        return 1000;
    }
}
