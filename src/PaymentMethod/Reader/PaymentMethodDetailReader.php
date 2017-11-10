<?php declare(strict_types=1);

namespace Shopware\PaymentMethod\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\DetailReaderInterface;
use Shopware\AreaCountry\Reader\AreaCountryBasicReader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\PaymentMethod\Factory\PaymentMethodDetailFactory;
use Shopware\PaymentMethod\Struct\PaymentMethodDetailCollection;
use Shopware\PaymentMethod\Struct\PaymentMethodDetailStruct;
use Shopware\Shop\Reader\ShopBasicReader;

class PaymentMethodDetailReader implements DetailReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var PaymentMethodDetailFactory
     */
    private $factory;

    /**
     * @var ShopBasicReader
     */
    private $shopBasicReader;

    /**
     * @var AreaCountryBasicReader
     */
    private $areaCountryBasicReader;

    public function __construct(
        PaymentMethodDetailFactory $factory,
        ShopBasicReader $shopBasicReader,
        AreaCountryBasicReader $areaCountryBasicReader
    ) {
        $this->factory = $factory;
        $this->shopBasicReader = $shopBasicReader;
        $this->areaCountryBasicReader = $areaCountryBasicReader;
    }

    public function readDetail(array $uuids, TranslationContext $context): PaymentMethodDetailCollection
    {
        if (empty($uuids)) {
            return new PaymentMethodDetailCollection();
        }

        $paymentMethodsCollection = $this->read($uuids, $context);

        $shops = $this->shopBasicReader->readBasic($paymentMethodsCollection->getShopUuids(), $context);

        $countries = $this->areaCountryBasicReader->readBasic($paymentMethodsCollection->getCountryUuids(), $context);

        /** @var PaymentMethodDetailStruct $paymentMethod */
        foreach ($paymentMethodsCollection as $paymentMethod) {
            $paymentMethod->setShops($shops->getList($paymentMethod->getShopUuids()));
            $paymentMethod->setCountries($countries->getList($paymentMethod->getCountryUuids()));
        }

        return $paymentMethodsCollection;
    }

    private function read(array $uuids, TranslationContext $context): PaymentMethodDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('payment_method.uuid IN (:ids)');
        $query->setParameter('ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new PaymentMethodDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new PaymentMethodDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
