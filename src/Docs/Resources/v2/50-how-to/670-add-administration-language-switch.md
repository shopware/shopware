[titleEn]: <>(Switching content language in administration)
[metaDescriptionEn]: <>(This HowTo will teach you how the content language is changed and what impacts these changes have.)
[hash]: <>(article:how_to_admin_language_switch)

## Overview

The administration's content language is one of Shopware's key USP's.
It enables users to switch between available languages. Its use will affect the whole administration.
For example, if you change the content language in the so called `language switch`, every listing or detail page will
update and use that new language to display and edit content.
To make sure your custom entity supports translations, look at [overview of translations in the DAL](./../2-internals/1-core/20-data-abstraction-layer/120-translations.md). 


## Change language in a listing

In order to change the language in an administration listing, you will have to add the `language switch` component to your smart bar:

```twig
{# foobar-list.html.twig #}

<sw-page>
    <template #language-switch>
        <sw-language-switch></sw-language-switch>
    </template>
</sw-page>
```

Although this slot already bears the name `language switch`, it is not yet fully functional.
The slot is used to place the `language switch` in the same place on all pages according to Shopware's conventions.
But why is it not served there by default? Firstly, not all components are translatable.
Additionally, pages do not know how to handle language switching themselves.
The `language switch` triggers the language change. Every request afterwards will then use the new language.
So, as our content is not automatically reloaded by default on switching the language, you will have to listen to the
`language switch's` change event:

```twig
<sw-language-switch @change="changeLanguage"></sw-language-switch>
``` 
```javascript
import template from './foobar-list.html.twig';
const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('foobar-list', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            items: null,
            isLoading: true
        }
    },

    computed: {
        customEntityRepository() {
            return this.repositoryFactory.create('foobar_custom_entity');
        }
    },

    methods: {
        changeLanguage() {
            this.getList();
        },

        getList() {
            this.isLoading = true;

            return this.customEntityRepository.search(new Criteria(), Shopware.Context.api).then((searchResult) => {
                this.items = searchResult;
                this.isLoading = false;
            })
        }
    }
});
```

## Changing languages on a detail page

To change the language on a detail page, you will have to add the `language switch` component into your smart bar as well:

```twig
{# foobar-detail.html.twig #}

<sw-page>
    <template #language-switch>
        <sw-language-switch></sw-language-switch>
    </template>
</sw-page>
```

In the listing, you will have to provide reloading logic by listening to the `change` event:

```twig
<sw-language-switch @change="loadItem"></sw-language-switch>
``` 

```javascript
import template from './foobar-detail.html.twig';
const { Component } = Shopware;

Component.register('foobar-detail', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            item: null
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        customEntityRepository() {
            return this.repositoryFactory.create('foobar_custom_entity');
        }
    },

    methods: {
        createdComponent() {
            this.loadItem();
        },

        loadItem() {
            return this.customEntityRepository.get(this.$route.params.id, Shopware.Context.api).then((entity) => {
                this.item = entity;
            });
        }
    }
});
```

This will reload the active entity on a detail page. Be aware, you may loose data here.
To prevent data loss, a modal will give you the opportunity to save unsaved changes, while switching the language: 

```twig
<sw-language-switch :saveChangesFunction="saveOnLanguageChange"
                    :abortChangeFunction="abortOnLanguageChange"
                    @change="loadItem">
</sw-language-switch>
``` 

```javascript
import template from './foobar-detail.html.twig';
const { Component } = Shopware;

Component.register('foobar-detail', {
    template,

    inject: ['repositoryFactory'],

    data() {
        return {
            item: null
        };
    },

    created() {
        this.createdComponent();
    },

    computed: {
        customEntityRepository() {
            return this.repositoryFactory.create('foobar_custom_entity');
        }
    },

    methods: {
        createdComponent() {
            this.loadItem();
        },

        loadItem() {
            return this.customEntityRepository.get(this.$route.params.id, Shopware.Context.api).then((entity) => {
                this.item = entity
            });
        },

        abortOnLanguageChange() {
            return this.customEntityRepository.hasChanges(this.item);
        },

        saveOnLanguageChange() {
            return this.customEntityRepository.save(this.item, Shopware.Context.api);
        }
    }
});
```
In case a language beside the system default language is displayed, it is customary to hint at it somehow.
In Shopware you can simply use the `sw-language-info` component to do that.
This component displays a short info text about the current selected language and its impacts on the current entity:

```twig
<sw-page class="foobar-detail">
    <template #content>
        <sw-card-view v-if="item">
            <sw-language-info :entityDescription="item.name"></sw-language-info>
        </sw-card-view>
    </template>
</sw-page>
```

## Change language in a create page

You simply don't. As previously explained, when switching the language every content needs to be reloaded.
On a creation page there is nothing to reload.
It is common to re-use the editing page for creation, but you have to make sure that you don't do it while creating.
In this case, simply disable the component using this attribute:

```twig
<sw-page>
    <template #language-switch>
        <sw-language-switch :disabled="isCreateMode"></sw-language-switch>
    </template>
</sw-page>
```

There is still a pitfall to bypass.
When you create a new entry using a different language than the system default language, you have to switch back to
it for the creation process:

```javascript
Component.extend('foobar-create', 'foobar-detail', {
    created() {
        if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
            this.languageStore.setCurrentId(this.languageStore.systemLanguageId)
        }
    }
});
```

The language info differs depending on its entity, so be sure to tell the `sw-language-info` component about it:

```twig
<sw-language-info :entityDescription="item.name"
                  :isNewEntity="isCreateMode">
</sw-language-info>
```

## Display translations in the storefront

You can simply display a translated field by reading that field from the translated relation:

```twig
{# @var customEntity \FooBar\CustomEntityEntity #}
{{ customEntity.translated.field }}
```
