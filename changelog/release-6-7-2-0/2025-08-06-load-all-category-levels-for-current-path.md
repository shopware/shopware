---
title: Load all category levels for current path
---
# Core
* Changed `\Shopware\Core\Content\Category\SalesChannel\NavigationRoute` to load all category levels in the path to the currently active category.
___
# Storefront
* Changed `storefront/layout/sidebar/category-navigation.html.twig` template to show all loaded category levels and don't stop on the configured level

___

# Upgrade Information

## Load all category levels for current path in NavigationRoute

The navigation route now loads all category levels in the path to the currently active category.
Before it only loaded the configured levels as well as parents and children of the currently active category.
However the "siblings" of all the parents were missing, which caused the navigation to be incomplete when you wanted to render the tree to the active path.
As a result now the sidebar navigation in the CMS element now expands to the full path to the currently active category.