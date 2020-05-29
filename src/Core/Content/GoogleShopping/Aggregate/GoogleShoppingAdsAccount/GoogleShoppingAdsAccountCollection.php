<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping\Aggregate\GoogleShoppingAdsAccount;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(GoogleShoppingAdsAccountEntity $entity)
 * @method void              set(string $key, GoogleShoppingAdsAccountEntity $entity)
 * @method GoogleShoppingAdsAccountEntity[]    getIterator()
 * @method GoogleShoppingAdsAccountEntity[]    getElements()
 * @method GoogleShoppingAdsAccountEntity|null get(string $key)
 * @method GoogleShoppingAdsAccountEntity|null first()
 * @method GoogleShoppingAdsAccountEntity|null last()
 */
class GoogleShoppingAdsAccountCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'google_shopping_ads_account_collection';
    }

    protected function getExpectedClass(): string
    {
        return GoogleShoppingAdsAccountEntity::class;
    }
}
