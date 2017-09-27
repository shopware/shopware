<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\ShopTemplate\Factory\ShopTemplateBasicFactory;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicCollection;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicStruct;

class ShopTemplateBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ShopTemplateBasicFactory
     */
    private $factory;

    public function __construct(
        ShopTemplateBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): ShopTemplateBasicCollection
    {
        if (empty($uuids)) {
            return new ShopTemplateBasicCollection();
        }

        $shopTemplatesCollection = $this->read($uuids, $context);

        return $shopTemplatesCollection;
    }

    private function read(array $uuids, TranslationContext $context): ShopTemplateBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('shop_template.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ShopTemplateBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ShopTemplateBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
