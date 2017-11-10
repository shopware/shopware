<?php declare(strict_types=1);

namespace Shopware\Customer\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\DetailReaderInterface;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Customer\Factory\CustomerDetailFactory;
use Shopware\Customer\Struct\CustomerDetailCollection;
use Shopware\Customer\Struct\CustomerDetailStruct;
use Shopware\CustomerAddress\Searcher\CustomerAddressSearcher;
use Shopware\CustomerAddress\Searcher\CustomerAddressSearchResult;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CustomerDetailReader implements DetailReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var CustomerDetailFactory
     */
    private $factory;

    /**
     * @var CustomerAddressSearcher
     */
    private $customerAddressSearcher;

    public function __construct(
        CustomerDetailFactory $factory,
        CustomerAddressSearcher $customerAddressSearcher
    ) {
        $this->factory = $factory;
        $this->customerAddressSearcher = $customerAddressSearcher;
    }

    public function readDetail(array $uuids, TranslationContext $context): CustomerDetailCollection
    {
        if (empty($uuids)) {
            return new CustomerDetailCollection();
        }

        $customersCollection = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('customer_address.customerUuid', $uuids));
        /** @var CustomerAddressSearchResult $addresses */
        $addresses = $this->customerAddressSearcher->search($criteria, $context);

        /** @var CustomerDetailStruct $customer */
        foreach ($customersCollection as $customer) {
            $customer->setAddresses($addresses->filterByCustomerUuid($customer->getUuid()));
        }

        return $customersCollection;
    }

    private function read(array $uuids, TranslationContext $context): CustomerDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('customer.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CustomerDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CustomerDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
