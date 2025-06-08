---
title: Landing page feature
issue: NEXT-12032
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
* Added the landing page indexer, event and message:
    * `\Shopware\Core\Content\LandingPage\DataAbstractionLayer\LandingPageIndexer`
    * `\Shopware\Core\Content\LandingPage\LandingPageEvents`
    * `\Shopware\Core\Content\LandingPage\Event\LandingPageIndexerEvent`
    * `\Shopware\Core\Content\LandingPage\DataAbstractionLayer\LandingPageIndexingMessage`
* Added landing page route for sales channel:
    * `\Shopware\Core\Content\LandingPage\SalesChannel\AbstractLandingPageRoute`
    * `\Shopware\Core\Content\LandingPage\Exception\LandingPageNotFoundException`
    * `\Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRoute`
    * `\Shopware\Core\Content\LandingPage\SalesChannel\LandingPageRouteResponse`
    * `\Shopware\Core\Content\LandingPage\SalesChannel\SalesChannelLandingPageDefinition`
* Added validator for the sales channel association to landing pages:
    * `Shopware\Core\Content\LandingPage\LandingPageValidator`
___
# Administration
* Added props `shouldShowActiveState`, `allowDuplicate` and `allowCreateWithoutPosition` to `sw-tree-item` component
* Added new slot `grip` to `sw-tree-item` component
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
* Added new optional property `headline` to `sw-category-layout-card` component
* Added new blocks to `sw-cms-layout-assignment-modal` so landing page layouts can be assigned to landing pages via cms modal:
    * `sw_cms_layout_assignment_modal_tab_landing_pages`
    * `sw_cms_layout_assignment_modal_landing_page_select`
    * `sw_cms_layout_assignment_modal_confirm_changes_text_landing_pages`
    * `sw_cms_layout_assignment_modal_confirm_changes_text_assigned_layouts_landing_pages`
* Changed `sw-category-tree` handling to `$set` and `$delete` methods
___
# Storefront
* Added the landing page controller with the seo url route:
    * `\Shopware\Storefront\Controller\LandingPageController`
    * `\Shopware\Storefront\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute`
    * `\Shopware\Storefront\Page\LandingPage\LandingPage`
    * `\Shopware\Storefront\Page\LandingPage\LandingPageLoader`
* Added the `updateLandingPageUrls` method to `\Shopware\Storefront\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener`
