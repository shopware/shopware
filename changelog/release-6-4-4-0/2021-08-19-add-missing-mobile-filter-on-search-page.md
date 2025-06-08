---
title: Add missing mobile filter on search page
issue: NEXT-14064
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Storefront
* Added new variables `listing` and `sidebar` to `Resources/views/storefront/element/cms-element-sidebar-filter.html.twig` to allow including the template manually without CMS page context.
* Changed template `Resources/views/storefront/page/search/search-pagelet.html.twig` to include `@Storefront/storefront/element/cms-element-sidebar-filter.html.twig` instead of `@Storefront/storefront/component/listing/filter-panel.html.twig`.
* Changed template `Resources/views/storefront/page/search/search-pagelet.html.twig` to no longer render main listing content inside `div` element `cms-element-sidebar-filter`. The listing container is now underneath the filter container.
