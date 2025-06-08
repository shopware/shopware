---
title: CMS entities version aware
issue: NEXT-13273
author: Jan Pietrzyk
author_email: j.pietrzyk@shopware.com 
author_github: @JanPietrzyk
---
# Core
* Add version fields to the primary key of all cms entities 
    * `\Shopware\Core\Content\Cms\CmsPageDefinition`
    * `\Shopware\Core\Content\Cms\Aggregate\CmsSlotDefinition`
    * `\Shopware\Core\Content\Cms\Aggregate\BlockDefinition`
    * `\Shopware\Core\Content\Cms\Aggregate\SectionDefinition`
* CMS version id as part of the foreign key constraint to cms pages
    * `\Shopware\Core\Content\Product\ProductDefinition`
    * `\Shopware\Core\Content\Category\CategoryDefinition`
    * `\Shopware\Core\Content\LandingPage\LandingPageDefinition`
___
# Upgrade Information

## Cms entities version aware

### Plugin updates

This change update the primary key of `cms_page`, `cms_slot`, `cms_block` and `cms_section` and the corresponding translation tables. If your plugin incorporates foreign keys to these tables you will need to update your migrations and dal entity definitions.

Please use `bin/console dal:validate` to see if you have to adjust your plugins anywhere.

#### Update

If your plugin is already installed the shopware core migration will take care of adjusting the foreign key. A new column `{TABLE_NAME}_version_id` is created, and the constraint widened. You will just have to add a version reference field in your definitions.

For a `cms_page` relation this would make these lines mandatory in your field definition like this:

```php
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;

new ReferenceVersionField(CmsPageDefinition::class);
```

#### Install

If your plugin is newly installed you should add a combined foreign key to your create table statement.

```sql
CREATE TABLE _TABLE_ IF NOT EXISTS
    `cms_page_id` binary(16) DEFAULT NULL, # the existing column
    `cms_page_version_id` binary(16) NOT NULL',# from noe on mandatory
    [....]
    KEY `_NAME_` (`cms_page_id`,`cms_page_version_id`),
    CONSTRAINT `_NAME_` FOREIGN KEY (`cms_page_id`, `cms_page_version_id`) REFERENCES `cms_page` (`id`, `version_id`) ON DELETE CASCADE ON UPDATE CASCADE # notice the two column on two column key
);

```

### Deployment notice

Due to the migration changing the product table as well, the update process might be slower than usual. 

