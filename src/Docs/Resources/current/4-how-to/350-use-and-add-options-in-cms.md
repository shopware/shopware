[titleEn]: <>(Use and add options in CMS)
[metaDescriptionEn]: <>(This HowTo will teach you to access options in CMS blocks and elements.)
[hash]: <>(article:use_and_add_options_in_cms)

This HowTo will teach you to access CMS options in the administration.

## Product box layout types

Product box layout types are the choices of products boxes that are used in listings, sliders or single products.
In a case you want to read the box types you can use the `cms-state` mixin in your component:

```js
const { Component, Mixin } = Shopware;

Component.register('foobar', {
    mixins: [
        // provides this.cmsPageState
        Mixin.getByName('cms-state')
    ],

    computed: {
        productBoxLayoutTypes() {
            // can also be directly access in the template without computed getter
            return this.cmsPageState.fieldOptions.productBoxLayoutType;
        }
    }
});
```

When you want to add a new box type you can execute the following statement any time in your plugin:

```js
Shopware.State.commit(
    'cmsPageState/setProductBoxLayoutType',
    {
        // technical name
        name: 'foobar',
        // snippet to display as label
        label: 'sw-cms.elements.productBox.config.label.layoutTypeFoobar'
    }
);
```

Therefore you also need add a corresponding snippet for it:

```json
{
    "sw-cms": {
        "elements": {
            "productBox": {
                "config": {
                    "label": {
                        "layoutTypeFoobar": "Foobar"
                    }
                }
            }
        }
    }
}
```

## Implement a new CMS field option state

In the imaginary case let us add an image mirror state that defines whether an image has to be either mirrored vertically, horizontally or not at all.
At first you choose a unique field option state e.g. `mediaMirrorMode`.
Second the field option state has to be added:

```js
// src/Administration/Resources/app/administration/src/module/sw-cms/state/cms-page.state.js

Shopware.State.registerModule('cmsPageState', {
    // ...
    state: {
        // ...
        fieldOptions: {
            // add initial modes in state initialization
            mediaMirrorMode: {
                none: {
                    label: 'sw-cms.detail.mediaMirrorMode.none'
                },
                horizontal: {
                    label: 'sw-cms.detail.mediaMirrorMode.horizontal'
                },
                vertical: {
                    label: 'sw-cms.detail.mediaMirrorMode.vertical'
                }
            }
        }
    },

    mutations: {
        // ...
        // add mutation to make it easily accessible and extensible
        setMediaMirrorMode(state, configuration) {
            // check the existence of configuration.name as it is used as key for the modes
            if (!('name' in configuration)) {
                return;
            }

            // clone object to remove the name from its properties without altering the input value
            configuration = { ...configuration };
            const name = configuration.name;
            delete configuration.name;

            // use Vue.set to make the state change reactive
            Vue.set(state.fieldOptions.mediaMirrorMode, name, {
                ...(state.fieldOptions.mediaMirrorMode[name] || {}),
                ...configuration
            });
        }
    }
});
``` 

Third add example code for usage and extensibility to this documentation page.
