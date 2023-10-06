---
title: Added tag administration
issue: NEXT-16790
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Added `CascadeDelete` flag to many to many association fields in `TagDefinition`
* Added migration to change foreign key referencing `tag` in many to many tables to `ON UPDATE CASCADE` and `ON DELETE CASCADE`
* Added tags many to many association in `RuleDefinition`
* Added service `FilterTagIdsService`
___
# Administration
* Added slots `bulk-additional` and `bulk-modals-additional` in `sw-entity-listing`
* Added controller `AdminTagController`
* Added module `sw-setting-tag`
* Added `sw-setting-tag-list` component
* Added `sw-settings-tag-detail-modal` component
* Added `sw-settings-tag-detail-assignments` component
* Added block `sw_settings_rule_detail_base_content_card_field_tags` in `sw-settings-rule-detail-base` template
