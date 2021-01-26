---
title: Landing page feature
issue: NEXT-12016
flag: FEATURE_NEXT_12016
author: Krispin Lütjann
author_email: k.luetjann@shopware.com 
author_github: King-of-Babylon
---
# Core
* Added definitions for landing page feature:
    * `\Shopware\Core\Content\LandingPage\LandingPageDefinition`
    * `\Shopware\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition`
    * `\Shopware\Core\Content\LandingPage\Aggregate\LandingPageTag\LandingPageTagDefinition`
    * `\Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition`
___
# Administration
* Added props `shouldShowActiveState`, `allowDuplicate` and `allowCreateWithoutPosition` to `sw-tree-item` component
* Added new blocks `sw_tree_items_active_state`, `sw_tree_items_actions_duplicate` and `sw_tree_items_actions_without_position` in `src/Administration/Resources/app/administration/src/app/component/tree/sw-tree-item/sw-tree-item.html.twig`
* Added `viewer`, `editor`, `creator` and `deleter` roles for landing page tree in `src/Administration/Resources/app/administration/src/module/sw-category/acl/index.js`
* Added new blocks `sw_category_tree`, `sw_landing_page_tree` and `sw_landing_page_content_view` in `src/Administration/Resources/app/administration/src/module/sw-category/page/sw-category-detail/sw-category-detail.html.twig`
* Added new functions `duplicateElement` to `sw-tree` and `sw-tree-item` component
* Added new computed prop `showEmptyState` to `sw-category-detail` component
* Added `sw-landing-page-tree` component in following files:
    * `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-landing-page-tree/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-landing-page-tree/sw-landing-page-tree.html.twig`
* Added `sw-landing-page-tree-view` component in following files:
    * `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-landing-page-view/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-category/component/sw-landing-page-view/sw-landing-page-view.html.twig`
* Added `sw-landing-page-detail-base` component in following files:
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-landing-page-detail-base/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-landing-page-detail-base/sw-landing-page-detail-base.html.twig`
* Added `sw-landing-page-detail-cms` component in following files:
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-landing-page-detail-cms/index.js`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-landing-page-detail-cms/sw-landing-page-detail-cms.html.twig`
    * `src/Administration/Resources/app/administration/src/module/sw-category/view/sw-landing-page-detail-cms/sw-landing-page-detail-cms.scss`
* Added landing page routes to `sw-category` module
* Changed `sw-category-tree` handling to `$set` and `$delete` methods

