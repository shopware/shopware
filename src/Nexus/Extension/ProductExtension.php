<?php

namespace Shopware\Nexus\Extension;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Product\Extension\ProductExtension as ProductApiExtension;
use Shopware\Product\Struct\ProductBasicStruct;
use Shopware\Search\QuerySelection;

class ProductExtension extends ProductApiExtension
{
    /**
     * @var Connection
     */
    private $connection;

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    public function getBasicFields(): array
    {
        $fields = parent::getBasicFields();

        return $fields;
    }

    public function hydrate(
        ProductBasicStruct $product,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
        parent::hydrate($product, $data, $selection, $translation);
    }
}