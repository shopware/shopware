---
title: Fix cascading DAL operations on associations, that use non id-named column as primary key
flag: FEATURE_NEXT_14872
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: JoshuaBehrens
---
# Core
* Changed fetching of primary keys in `\Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver` to not rely on the name `id` of primary keys. Therefore, it allows other names for primary keys which are likely when you use foreign keys to other entities as primary key.
