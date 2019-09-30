[titleEn]: <>(Add CMS sections)

We made breaking changes to the data structure of the shopping expiriences by adding **sections**. 

Sections divide the working platform into areas eg. a sidebar- and a main-content.
To realise this we had to change the data structure of a page by adding the section entity between the page and the blocks. The new structure looks like this: `page->sections->blocks->slots`. A page has one or multiple section in which one or multiple blocks can be placed.

If you have existsing pages it is strongly recommended to migrate your data via `bin/console database:migrate --all Shopware\\`. Migaration `Migration1568120302CmsBlockUpdate` will provide each of your pages with a default section where all existing blocks will be stored.
