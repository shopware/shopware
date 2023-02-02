[titleEn]: <>(Step 10: Final adjustments)
[hash]: <>(article:bundle_final)

You're almost done with the whole plugin. There's just one more thing to do before being completely done.

## Removing the plugin's data

Upon installation, you setup database tables necessary for your plugin to function properly.
When the shop manager uninstalls your plugin though, the database tables will always remain. Since the shop manager will be asked if he wants to keep
the plugin's data when uninstalling it, this **can** be good. But what if he actually wanted to fully remove your plugin?

In that case, you have to remove everything connected to your plugin, such as the database tables.
You do this in your plugin base class' `uninstall` method.

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;

class BundleExample extends Plugin
{
    public function activate(ActivateContext $activateContext): void
    {
        $registry = $this->container->get(EntityIndexerRegistry::class);
        $registry->sendIndexingMessage(['product.indexer']);
    }

    public function uninstall(UninstallContext $context): void
    {
        parent::uninstall($context);

        if ($context->keepUserData()) {
            return;
        }

        $connection = $this->container->get(Connection::class);

        $connection->executeUpdate('DROP TABLE IF EXISTS `swag_bundle_product`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `swag_bundle_translation`');
        $connection->executeUpdate('DROP TABLE IF EXISTS `swag_bundle`');
        $connection->executeUpdate('ALTER TABLE `product` DROP COLUMN `bundles`');
    }
}
```

The line `$context->keepUserData()` contains a boolean about whether or not the shop manager intended to keep the data that came with this plugin.
If he chose "No", this will be `false` and you should remove all database tables from your plugin again.

That's it. Your plugin is now fully functional!
Go ahead and try it out. Create a new bundle in the Administration, assign products to it, open the products in the Storefront, put them into the cart and see your
bundle discount being applied!

## Exercise

If you want to try out what you've learned in this series, you can add more functionality to your plugin.
An example task would be the following:

**Create a new configuration for your bundle to decide if it should be stackable or not.**
This means:
- Add a new column to your database table `swag_bundle`
- Add this new field to your `FieldDefinition`
- Add this new field to your `swag-bundle-detail` component in the Administration
- Consider this new field in the checkout

## Source

There's a GitHub repository available, containing the full example source being used in this tutorial!
Check it out [here](https://github.com/shopware/swag-docs-bundle-example).
