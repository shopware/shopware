<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingMerchantAccount;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(GoogleShoppingMerchantAccountEntity $entity)
 * @method void              set(string $key, GoogleShoppingMerchantAccountEntity $entity)
 * @method GoogleShoppingMerchantAccountEntity[]    getIterator()
 * @method GoogleShoppingMerchantAccountEntity[]    getElements()
 * @method GoogleShoppingMerchantAccountEntity|null get(string $key)
 * @method GoogleShoppingMerchantAccountEntity|null first()
 * @method GoogleShoppingMerchantAccountEntity|null last()
 */
class GoogleShoppingMerchantAccountCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'google_shopping_merchant_account_collection';
    }

    protected function getExpectedClass(): string
    {
        return GoogleShoppingMerchantAccountEntity::class;
    }
}
