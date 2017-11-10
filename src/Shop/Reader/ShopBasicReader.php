<?php declare(strict_types=1);

namespace Shopware\Shop\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Shop\Factory\ShopBasicFactory;
use Shopware\Shop\Struct\ShopBasicCollection;
use Shopware\Shop\Struct\ShopBasicStruct;

class ShopBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var ShopBasicFactory
     */
    private $factory;

    public function __construct(
        ShopBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): ShopBasicCollection
    {
        if (empty($uuids)) {
            return new ShopBasicCollection();
        }

        $shopsCollection = $this->read($uuids, $context);

        return $shopsCollection;
    }

    private function read(array $uuids, TranslationContext $context): ShopBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('shop.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ShopBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ShopBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
