[titleEn]: <>(Add custom entity to administration search)
[metaDescriptionEn]: <>(This HowTo will show how to add a tag in the global search bar and use it to search for custom entities)
[hash]: <>(article:how_to_custom_entity_admin_search)

## Overview

Developing a customization that has a frequently visited entity listing takes advantage from a shortcut using the global search.
Think twice about adding this shortcut because if every plugin adds there own search tag it gets cluttered.
There are two different ways how the global search works:

* Global search without type specification
* Typed global search

They only differ in the API they use and get displayed in a slightly different way.


## Search API

To support an entity in the untyped global search the definition in the symfony container needs the tag `shopware.composite_search.definition`.
The priority of the tag defines the order in the search order.

The typed global search needs an instance of the JavaScript class `ApiService` with the key of the entity in camel case suffixed with `Service`.
E.g. The service key is `fooBarService` when requesting a service for `foo_bar`.
Every entity definition gets automatically an instance in the injection container but can be overridden so there is no additional work needed.


## Administration UI

### Add the search tag

The search tag displays the entity type that is used in the typed search and is a clickable button to switch from the untyped to the typed search.
To add the tag a service decorator is used to add a type to the `searchTypeService`:

```javascript
const { Application } = Shopware;

Application.addServiceProviderDecorator('searchTypeService', searchTypeService => {
    searchTypeService.upsertType('foo_bar', {
        entityName: 'foo_bar',
        entityService: 'fooBarService',
        placeholderSnippet: 'foo-bar.general.placeholderSearchBar',
        listingRoute: 'foo.bar.index'
    });

    return searchTypeService;
});
```

The key and `entityName` is used as the same to change also existing types.
The `entityService` is used for the typed search.
This service can be overridden with an own implementation for customization.
The `placeholderSnippet` is a translation key that is shown when no search term is entered.
The `listingRoute` is used to show a link to continue the search in the module specific listing view.


### Add the search result item

By default the search bar does not know how to display the result items so a current search request will not show any result.
In order to declare a search result view the `sw-search-bar-item` template has to be altered like this:
 
`sw-search-bar-item.html.twig`
```twig
{% block sw_search_bar_item_cms_page %}
    {% parent %}

    <router-link v-else-if="type === 'foo_bar'"
                 v-bind:to="{ name: 'foo.bar.detail', params: { id: item.id } }"
                 ref="routerLink"
                 class="sw-search-bar-item__link">
        {% block sw_search_bar_item_foo_bar_label %}
            <span class="sw-search-bar-item__label">
                <sw-highlight-text v-bind:searchTerm="searchTerm"
                                   v-bind:text="item.name">
                </sw-highlight-text>
            </span>
        {% endblock %}
    </router-link>
{% endblock %}
```

`index.js`
```javascript
import template from './sw-search-bar-item.html.twig';

Shopware.Component.override('sw-search-bar-item', {
    template
})
```

The `sw_search_bar_item_cms_page` block is used as it is the last block but it is not important which shopware type is extended as long as the vue else-if structure is kept working.


### Add custom show more results link

By default the search bar tries to resolve to the registered listing route.
If your entity can be searched externally you can edit the `sw-search-more-results` or `sw-search` components as well:

`sw-search-more-results.html.twig`
```twig
{% block sw_search_more_results %}
    <template v-if="result.entity === 'foo_bar'">
        There are so many hits.
        <a :href="'https://my.erp.localhost/?q=' + searchTerm"
           class="sw-search-bar-item__link"
           target="_blank">
             Look it directly up
        </a>
        in the ERP instead.
    </template>
    <template v-else>
        {% parent %}
    </template>
{% endblock %}
```

```javascript
`index.js`
import template from './sw-search-more-results.html.twig';

Shopware.Component.override('sw-search-more-results', {
    template
})
```


### Potential pitfalls

In case of a tag with a technical name the translation is missing:
```json
{
    "global": {
        "entities": {
            "foo_bar": "Foobar | Foobars"
        }
    }
}
```

To change the color of the tag or the icon in the untyped global search a module has to be registered with an entity reference in the module:

```javascript
Shopware.Module.register('any-name', {
    color: '#ff0000',
    icon: 'default-basic-shape-triangle',
    entity: 'foo_bar',
})
```
