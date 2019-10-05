[titleEn]: <>(Switching languages in administration and storefront)
[metaDescriptionEn]: <>(This HowTo will teach you how the editing language is changed and what impacts these changes have.)

## Overview

The editing language of the administration is one of the key USPs of its user interface.
It is present all over the application.
The most important fact is that it is used change for everything at once to keep editing consistent.
For example if you change the editing language in the language dropdown every listing or detail page will update and use that new language to display and edit content.
To make sure your custom entity supports translations look at the [overview of translations in the DAL](../../2-internals/1-core/20-data-abstraction-layer/120-translations.md). 


## Change language in a listing

To change the language in an administration listing you have to add the language switching component into your action bar:

```twig
{# foobar-list.html.twig #}

<sw-page>
    <template #language-switch>
        <sw-language-switch></sw-language-switch>
    </template>
</sw-page>
```

Although the slot already has the name it does not serve the language switch itself.
The slot is used to place the language switcher consistently on all pages at the same place.
But why is it not served there by default.
At first not every component is translatable and should not look like it supports it.
And the second reason is that the page does not know what to do when the language switches.
The language switcher just tells the administration application to change the language and every request afterwards uses the new language.
So as our content is not automatically reloaded on switching the language you have to listen to the change event of the language switcher like this:

```twig
<sw-language-switch @change="changeLanguage"></sw-language-switch>
``` 
```javascript
import template from './foobar-list.html.twig';

Shopware.Component.register('foobar-list', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    data() {
        return {
            items: null,
            isLoading: true
        }
    },

    computed: {
        customEntityRepository() {
            return this.repositoryFactory.create('foobar_custom_entity')
        }
    },

    methods: {
        changeLanguage(languageId) {
            this.context.languageId = languageId;
            this.getList()
        },

        getList() {
            this.isLoading = true;

            return this.customEntityRepository.search(new Shopware.Data.Criteria, this.context).then(searchResult => {
                this.items = searchResult;
                this.isLoading = false;
            })
        }
    }
});
```


## Change language in an editing page

To change the language in an administration editing page you have to add the language switching component into your action bar as well:

```twig
{# foobar-detail.html.twig #}

<sw-page>
    <template #language-switch>
        <sw-language-switch></sw-language-switch>
    </template>
</sw-page>
```

Like in the listing you have to provide a reloading functionality by listening to the on-change event:

```twig
<sw-language-switch @change="loadItem"></sw-language-switch>
``` 

```javascript
import template from './foobar-detail.html.twig';

Shopware.Component.register('foobar-detail', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    data() {
        return {
            item: null
        };
    },

    created() {
        this.loadItem();
    },

    computed: {
        customEntityRepository() {
            return this.repositoryFactory.create('foobar_custom_entity');
        }
    },

    methods: {
        loadItem() {
            return this.customEntityRepository.get(this.$route.params.id, this.context).then(entity => {
                this.item = entity
            });
        }
    }
});
```

This will now reload your entity on a language switch.
But be aware of that this might end up in data loss.
To prevent that the language switch component can cancel the language switch if you provide callbacks that check for changes:

```twig
<sw-language-switch :saveChangesFunction="saveOnLanguageChange"
                    :abortChangeFunction="abortOnLanguageChange"
                    @change="loadItem">
</sw-language-switch>
``` 

```javascript
import template from './foobar-detail.html.twig';

Shopware.Component.register('foobar-detail', {
    template,

    inject: [
        'repositoryFactory',
        'context'
    ],

    data() {
        return {
            item: null
        };
    },

    created() {
        this.loadItem();
    },

    computed: {
        customEntityRepository() {
            return this.repositoryFactory.create('foobar_custom_entity');
        }
    },

    methods: {
        changeLanguage(languageId) {
            this.context.languageId = languageId;
            this.loadItem()
        },

        loadItem() {
            return this.customEntityRepository.get(this.$route.params.id, this.context).then(entity => {
                this.item = entity
            })
        },

        abortOnLanguageChange() {
            return this.customEntityRepository.hasChanges(this.item)
        },

        saveOnLanguageChange() {
            return this.customEntityRepository.save(this.item, this.context)
        }
    }
});
```

It is regarded a good way to communicate the current selected language as different values are displayed than usual.
For that you can simply use the language info component.
This component displays a short info text about the current selected language and its impacts on the current entity.
You can see it regularly above an other content:

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

You simply don't.
As previously explain on switching the language every content needs to be reloaded.
On a creation page you have nothing to reload.
As it is a common use-case to re-use the editing page for creation you have to make sure that you don't reload the language.
Simply disable the component using the attribute:

```twig
<sw-page>
    <template #language-switch>
        <sw-language-switch disabled="disabled"></sw-language-switch>
    </template>
</sw-page>
```

There is still a pitfall to bypass.
When you create a new entry using a different language than the default language there is not fallback value displayed as no default value is given.
To ensure a translation for the fallback language you simply force a change to the default language before editing starts:

```javascript
Shopware.Component.register('foobar-detail', {
    created() {
        if (this.languageStore.getCurrentId() !== this.languageStore.systemLanguageId) {
            this.languageStore.setCurrentId(this.languageStore.systemLanguageId)
        }
    },

    computed: {
        languageStore() {
            return Shopware.State.getStore('language')
        }
    }
});
```

The language info display a slightly different text for a new entry so be sure to tell the language info component it is about editing a new entry:

```twig
<sw-language-info :entityDescription="item.name"
                  :isNewEntity="true">
</sw-language-info>
```


## Display translations in the storefront

You can simply display a translated field by reading that field from the translated relation:

```twig
{# @var customEntity \FooBar\CustomEntityEntity #}
{{ customEntity.translated.field }}
```
