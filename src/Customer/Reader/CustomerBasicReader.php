<?php declare(strict_types=1);

namespace Shopware\Customer\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Factory\CustomerBasicFactory;
use Shopware\Customer\Struct\CustomerBasicCollection;
use Shopware\Customer\Struct\CustomerBasicStruct;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CustomerBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var CustomerBasicFactory
     */
    private $factory;

    public function __construct(
        CustomerBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): CustomerBasicCollection
    {
        if (empty($uuids)) {
            return new CustomerBasicCollection();
        }

        $customersCollection = $this->read($uuids, $context);

        return $customersCollection;
    }

    private function read(array $uuids, TranslationContext $context): CustomerBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('customer.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CustomerBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CustomerBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
