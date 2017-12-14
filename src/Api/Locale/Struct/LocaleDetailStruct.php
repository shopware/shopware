<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Struct;

use Shopware\Api\Locale\Collection\LocaleTranslationBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;
use Shopware\Api\User\Collection\UserBasicCollection;

class LocaleDetailStruct extends LocaleBasicStruct
{
    /**
     * @var LocaleTranslationBasicCollection
     */
    protected $translations;

    /**
     * @var ShopBasicCollection
     */
    protected $shops;

    /**
     * @var UserBasicCollection
     */
    protected $users;

    public function __construct()
    {
        $this->translations = new LocaleTranslationBasicCollection();

        $this->shops = new ShopBasicCollection();

        $this->users = new UserBasicCollection();
    }

    public function getTranslations(): LocaleTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(LocaleTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getShops(): ShopBasicCollection
    {
        return $this->shops;
    }

    public function setShops(ShopBasicCollection $shops): void
    {
        $this->shops = $shops;
    }

    public function getUsers(): UserBasicCollection
    {
        return $this->users;
    }

    public function setUsers(UserBasicCollection $users): void
    {
        $this->users = $users;
    }
}
