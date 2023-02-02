[titleEn]: <>(Magento profile)
[hash]: <>(article:migration_magento)

To migrate the data from a Magento 1.9.x system, Shopware has created a [Magento migration profile plugin](https://github.com/shopwareLabs/SwagMigrationMagento) for this case.
This profile is currently migrates following data:
* Languages
* Customer groups
* Categories
* Countries
* Currencies
* Shop structure (store views etc.)
* Customers
* Orders
* Media
* Manufacturer / supplier
* Property groups
* Products + variants
* Product reviews
* Seo urls
* Cross- and up-selling relations

Currently the profile downloads all media files, but does not download order documents.
Important: In order to download the WYSIWYG-media-files, Shopware 6 has to be located on the same server as the Magento system.
All data will be migrated via the Magento database. Like the Shopware 5.x profiles it can be [extended](./../../../50-how-to/520-extend-shopware-migration-profile.md)
and [decorated](./../../../50-how-to/550-decorate-shopware-migration-converter.md) by plugin developers, because it is
implemented in the same way (have a look at the [migration concept](./010-introduction.md) for more information).
