[titleEn]: <>(Unique default ids)

We made all IDs defined in `Shopware\Core\Defaults.php` unique, so the Ids changed.

If you experience some problems with logging in to the Admin after rebasing your branch please check the localStorage for the key sw-admin-current-language and delete this key.

After that it should work as before.
