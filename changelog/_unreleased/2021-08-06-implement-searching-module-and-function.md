---
title: Implement searching for module and function
issue: NEXT-16485
flag: FEATURE_NEXT_6040
---
# Administration
* Changed method `loadResults` in `src/app/component/structure/sw-search-bar/index.js`.
* Added some computed in `src/app/component/structure/sw-search-bar/index.js`
    * `salesChannelRepository`
    * `salesChannelTypeRepository`  
    * `salesChannelCriteria`
    * `canViewSalesChannels`
    * `canCreateSalesChannels`  
    * `moduleRegistry`
    * `searchableModules`
* Changed method `createdComponent` in `src/app/component/structure/sw-search-bar/index.js` to load sales channel.  
* Added method `getModuleEntities` in `src/app/component/structure/sw-search-bar/index.js` to filter entities with search term.
* Added method `getDefaultMatchSearchableModules` in `src/app/component/structure/sw-search-bar/index.js` to get default matching searchable modules.  
* Added method `loadSalesChannel` in `src/app/component/structure/sw-search-bar/index.js` to load sales channel.
* Added method `loadSalesChannelType` in `src/app/component/structure/sw-search-bar/index.js` to load sales channel type.  
* Added method `getSalesChannelsBySearchTerm` in `src/app/component/structure/sw-search-bar/index.js` to filter sales channel by term. 
* Changed block `sw_search_bar_results_list_bar_item` in `src/app/component/structure/sw-search-bar/sw-search-bar.html.twig` to update `v-if` directive to check entity without type is `module`.
* Added some computed in `src/app/component/structure/sw-search-bar-item/index.js`
    * `moduleName`
    * `routeName`
    * `iconName`
    * `iconColor`
    * `shortcut`
* Changed block `sw_search_bar_item_icon` in `src/app/component/structure/sw-search-bar-item/sw-search-bar-item.html.twig` to change property at `sw-icon` component.
* Added block `sw_search_bar_item_module` in `src/app/component/structure/sw-search-bar-item/sw-search-bar-item.html.twig` to handle the UI.
* Changed some modules to add function `searchMatcher` to get matching entities.
    * `sw-category` in `src/module/sw-category/index.js`
    * `sw-extension` in `src/module/sw-extension/index.js`
___
# Upgrade Information
## Adding search matcher configuration
When you want to your module appear on the search bar, you can define the  `searchMatcher` in the module’s metadata, otherwise, a default `searchMatcher `will be used as it will check your module’s metadata label if it’s matched with the search term, The search function should return an array of results that will appear on the search bar.

Example usage:

```
Module.register('sw-module-name', {
  ...
  
  searchMatcher: (regex, labelType, manifest) => {
    const match = labelType.toLowerCase().match(regex);

    if (!match) {
      return false;
    }

    return [
      {
        icon: manifest.icon,
        color: manifest.color,
        label: labelType,
        entity: '...',
        route: '...',
        privilege: '...',
      },
    ];
  }
})
```
