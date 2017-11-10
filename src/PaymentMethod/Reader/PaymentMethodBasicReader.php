<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\BasicReaderInterface;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\PaymentMethod\Factory\PaymentMethodBasicFactory;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodBasicStruct;

class PaymentMethodBasicReader implements BasicReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var PaymentMethodBasicFactory
     */
    private $factory;

    public function __construct(
        PaymentMethodBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function readBasic(array $uuids, TranslationContext $context): PaymentMethodBasicCollection
    {
        if (empty($uuids)) {
            return new PaymentMethodBasicCollection();
        }

        $paymentMethodsCollection = $this->read($uuids, $context);

        return $paymentMethodsCollection;
    }

    private function read(array $uuids, TranslationContext $context): PaymentMethodBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('payment_method.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new PaymentMethodBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new PaymentMethodBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
