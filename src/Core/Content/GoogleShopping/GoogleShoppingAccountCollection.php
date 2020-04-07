<?php declare(strict_types=1);

namespace Shopware\Core\Content\GoogleShopping;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void              add(GoogleShoppingAccountEntity $entity)
 * @method void              set(string $key, GoogleShoppingAccountEntity $entity)
 * @method GoogleShoppingAccountEntity[]    getIterator()
 * @method GoogleShoppingAccountEntity[]    getElements()
 * @method GoogleShoppingAccountEntity|null get(string $key)
 * @method GoogleShoppingAccountEntity|null first()
 * @method GoogleShoppingAccountEntity|null last()
 */
class GoogleShoppingAccountCollection extends EntityCollection
{
    public function filterBySalesChannelId(string $id): GoogleShoppingAccountCollection
    {
        return $this->filter(static function (GoogleShoppingAccountEntity $googleShoppingAccount) use ($id) {
            return $googleShoppingAccount->getSalesChannelId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'google_shopping_account_collection';
    }

    protected function getExpectedClass(): string
    {
        return GoogleShoppingAccountEntity::class;
    }
}
