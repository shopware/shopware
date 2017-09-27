<?php declare(strict_types=1);

namespace Shopware\Currency\Factory;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Struct\CurrencyBasicStruct;
use Shopware\Currency\Struct\CurrencyDetailStruct;
use Shopware\Framework\Factory\ExtensionRegistryInterface;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Shopware\Shop\Factory\ShopBasicFactory;

class CurrencyDetailFactory extends CurrencyBasicFactory
{
    /**
     * @var ShopBasicFactory
     */
    protected $shopFactory;

    public function __construct(
        Connection $connection,
        ExtensionRegistryInterface $registry,
        ShopBasicFactory $shopFactory
    ) {
        parent::__construct($connection, $registry);
        $this->shopFactory = $shopFactory;
    }

    public function getFields(): array
    {
        $fields = array_merge(parent::getFields(), $this->getExtensionFields());
        $fields['_sub_select_shop_uuids'] = '_sub_select_shop_uuids';

        return $fields;
    }

    public function hydrate(
        array $data,
        CurrencyBasicStruct $currency,
        QuerySelection $selection,
        TranslationContext $context
    ): CurrencyBasicStruct {
        /** @var CurrencyDetailStruct $currency */
        $currency = parent::hydrate($data, $currency, $selection, $context);
        if ($selection->hasField('_sub_select_shop_uuids')) {
            $uuids = explode('|', (string) $data[$selection->getField('_sub_select_shop_uuids')]);
            $currency->setShopUuids(array_values(array_filter($uuids)));
        }

        return $currency;
    }

    public function joinDependencies(QuerySelection $selection, QueryBuilder $query, TranslationContext $context): void
    {
        parent::joinDependencies($selection, $query, $context);

        if ($shops = $selection->filter('shops')) {
            $mapping = QuerySelection::escape($shops->getRoot() . '.mapping');

            $query->leftJoin(
                $selection->getRootEscaped(),
                'shop_currency',
                $mapping,
                sprintf('%s.uuid = %s.currency_uuid', $selection->getRootEscaped(), $mapping)
            );
            $query->leftJoin(
                $mapping,
                'shop',
                $shops->getRootEscaped(),
                sprintf('%s.shop_uuid = %s.uuid', $mapping, $shops->getRootEscaped())
            );

            $this->shopFactory->joinDependencies($shops, $query, $context);

            $query->groupBy(sprintf('%s.uuid', $selection->getRootEscaped()));
        }

        if ($selection->hasField('_sub_select_shop_uuids')) {
            $query->addSelect('
                (
                    SELECT GROUP_CONCAT(mapping.shop_uuid SEPARATOR \'|\')
                    FROM shop_currency mapping
                    WHERE mapping.currency_uuid = ' . $selection->getRootEscaped() . '.uuid
                ) as ' . QuerySelection::escape($selection->getField('_sub_select_shop_uuids'))
            );
        }
    }

    public function getAllFields(): array
    {
        $fields = parent::getAllFields();
        $fields['shops'] = $this->shopFactory->getAllFields();

        return $fields;
    }

    protected function getExtensionFields(): array
    {
        $fields = parent::getExtensionFields();

        foreach ($this->getExtensions() as $extension) {
            $extensionFields = $extension->getDetailFields();
            foreach ($extensionFields as $key => $field) {
                $fields[$key] = $field;
            }
        }

        return $fields;
    }
}
