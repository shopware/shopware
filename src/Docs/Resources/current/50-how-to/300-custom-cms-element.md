[titleEn]: <>(Adding a custom CMS element)
[metaDescriptionEn]: <>(This HowTo will teach you to create a new CMS element via a plugin.)
[hash]: <>(article:how_to_custom_cms_element)

This article will teach you how to create a new CMS element via plugin.

## Setup

You won't learn how to create a plugin in this guide, head over to our [developer guide](./../20-developer-guide/10-plugin-base.md) to
create your first plugin.
Also, you should know how to handle the "Shopping Experiences" module in the administration first.
Have a look at our documentation about the "Shopping Experiences" module [here](https://docs.shopware.com/en/shopware-6-en/content/ShoppingExperiences).
Nothing, but these two requirements, are necessary to get started on this subject.

The plugin in this example will be named `CustomCmsElement`.

## Custom element

Imagine you'd want to create a new element to display a Dailymotion video.
The shop manager can configure the link of the video to be shown.

That's exactly what you're going to build in this HowTo.

Creating a new element requires you to extend the administration.

### Injecting into the administration
    
The main entry point to customize the administration via plugin is the `main.js` file.
It has to be placed into a `<plugin root>/src/Resources/app/administration/src` directory in order to be automatically found by the Shopware
platform.

Create this `main.js` file for now, it will be used later.

### Registering a new element

Your plugin's structure should always match the core's structure. When thinking about creating a new element, you should
recreate the file tree like in the core for your plugin.
Thus, recreate [this structure](https://github.com/shopware/platform/tree/master/src/Administration/Resources/app/administration/src/module/sw-cms/elements) in your plugin:
`<plugin root>/src/Resources/app/administration/src/module/sw-cms/elements`

In there, you create a directory for each new element you want to create, in this example a directory `dailymotion` is created.

Now create a new file `index.js` inside the `dailymotion` directory, since it will be loaded
when importing this element in your `main.js`.
Speaking of that, right after having created the `index.js` file, you can actually import your new element's directory in
the `main.js` file already:

```js
import './module/sw-cms/elements/dailymotion';
```

Now open up your empty `index.js` file. In order to register a new element to the system, you have to call the method `registerCmsElement`
of the [cmsService](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/module/sw-cms/service/cms.service.js).
Since it's available in the Dependency Injection Container, you can fetch it from there.

First of all, access our `Applicaton` wrapper, which will grant you access to the DI container. So go ahead and fetch the `cmsService` from it and call the
mentioned `registerCmsElement` method.

```js
Shopware.Service('cmsService').registerCmsElement();
```

The method `registerCmsElement` takes a configuration object, containing the following necessary data:
name
 : The technical name of your element. Will be used for the template loading later on.
 
label
 : A name to be shown for your element in the User Interface. Preferably as a snippet key.
 
component
 : The Vue component to be used when rendering your actual element in the administration.
 
configComponent
 : The Vue component defining the "configuration detail" page of your element.
 
previewComponent
 : The Vue component to be used in the "list of available elements". Just shows a tiny preview of what your element
  would look like if it was used.
  
defaultConfig
: A default configuration to be applied to this element. Must be an object containing properties matching the used variable
 names, containing the default values.


Go ahead and create this configuration object yourself.
Here's what it should look like after having set all of those options:
```js
Shopware.Service('cmsService').registerCmsElement({
    name: 'dailymotion',
    label: 'sw-cms.elements.customDailymotionElement.label',
    component: 'sw-cms-el-dailymotion',
    configComponent: 'sw-cms-el-config-dailymotion',
    previewComponent: 'sw-cms-el-preview-dailymotion',
    defaultConfig: {
        dailyUrl: {
            source: 'static',
            value: ''
        }
    }
});
```

The property `name` does not require further explanation.
But you need to create a snippet files in you plugin directory for the `label` property.

To do this, create a folder with the name `snippet` in your `sw-cms` folder. After that, create the files for the languages. For example `de-DE.json` and `en-GB.json`.

The content of your snippet file should look something like this:

```json
{
  "sw-cms": {
    "elements": {
       "customDailymotionElement": {
        "label": "Dailymotion video"
      }
    }
  }
}
```

For more information about snippets, [click here](./245-adding-snippets.md).

For all three fields `component`, `configComponent` and `previewComponent`, components that do not **yet** exist were applied. Those will be created
in the next few steps as well.
The `defaultConfig` defines the default values for the element's configuration. There will be a text field to enter a Dailymotion video ID called `dailyUrl`.

Now you have to create the three missing components, let's start with the preview component.

### Preview

Create a new directory `preview` in your element's directory `dailymotion`. In there, create a new file `index.js`, just like for all components.
Then register your component, using the `Component` wrapper.
This HowTo will not explain how a custom component can be created though, so head over to the official HowTo about [creating a custom component](./280-custom-component.md)
to learn this first.

```js
import template from './sw-cms-el-preview-dailymotion.html.twig';
import './sw-cms-el-preview-dailymotion.scss';

const { Component } = Shopware;

Component.register('sw-cms-el-preview-dailymotion', {
    template
});
```

Just like most components, it has a custom template and some styles.
Focus on the template first, create a new file `sw-cms-el-preview-dailymotion.html.twig`.

So, what do you want to show here? Maybe the default 'mountain' preview image, that's already being used for the `image` element.
On top of that, you could place our icon `multicolor-action-play`. Head over to your [icon library](https://component-library.shopware.com/#/icons/) to find this icon.

That means: You'll need a container to contain both the image and the icon.
In there, you create an `img` tag and use the [sw-icon component](https://component-library.shopware.com/#/components/sw-icon) to display the icon.

```twig
{% block sw_cms_element_dailymotion_preview %}
    <div class="sw-cms-el-preview-dailymotion">
        <img class="sw-cms-el-preview-dailymotion-img"
             :src="'customcmselement/static/img/background_dailymotion_preview.jpg' | asset">

        <img class="sw-cms-el-preview-dailymotion-icon"
             :src="'customcmselement/static/img/dailymotion.svg' | asset">
    </div>
{% endblock %}
```

The icon would now be displayed beneath the image, so let's add some styles for this by creating the file `sw-cms-el-preview-dailymotion.scss`.

The container needs to have a `position: relative;` style. This is necessary, so the child can be positioned absolutely and will do so
relative to the container's position.
Thus, the icon receives a `position: absolute;` style, plus some `top` and `left` values to center it.

```scss
.sw-cms-el-preview-dailymotion {
    position: relative;

    .sw-cms-el-preview-dailymotion-img {
        display: block;
        max-width: 100%;
    }

    .sw-cms-el-preview-dailymotion-icon {
        $icon-height: 50px;
        $icon-width: $icon-height;
        position: absolute;
        height: $icon-height;
        width: $icon-width;

        left: calc(50% - #{$icon-width/2});
        top: calc(50% - #{$icon-height/2});
    }
}
```

The centered positioning will be done by translating the elements by 50% via `top` and `left` properties. Since that would be 50% from the upper left corner of the icon, this wouldn't really center
the icon yet. Subtract the half of the icon's width and height and then you're fine.

One last thing: Import your preview component in your element's `index.js` file, so it's loaded.

```js
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    // ...
});
```

### Rendering the element

The next would be the main component `sw-cms-el-dailymotion`, the one to be rendered when the shop manager actually decided to use your element by clicking
on the preview.
Now, you want to show the actually configured video here now.
Start with the basic again, create a new directory `component`, in there a new file `index.js` and then register your component `sw-cms-el-dailymotion`.

```js
import template from './sw-cms-el-dailymotion.html.twig';
import './sw-cms-el-dailymotion.scss';

Shopware.Component.register('sw-cms-el-dailymotion', {
    template
});
```

In addition, create the template file `sw-cms-el-dailymotion.html.twig` and the `.scss` file `sw-cms-el-dailymotion.scss`.

The template doesn't have to include a lot. Having a look at how Dailymotion video embedding works, you just have to add an `iframe`
with an `src` attribute pointing to the video.

```twig
{% block sw_cms_element_dailymotion %}
    <div class="sw-cms-el-dailymotion">
        <div class="sw-cms-el-dailymotion-iframe-wrapper">
            <iframe frameborder="0"
                    type="text/html"
                    width="100%"
                    height="100%"
                    :src="dailyUrl">
            </iframe>
        </div>
    </div>
{% endblock %}
```

You can't just use a static `src` here, since the shop manager wants to configure the video he wants to show. Thus, we're fetching
that link via VueJS now.

Let's add the code to provide the `src` for the iframe. For this case you're going to use a [computed property](https://vuejs.org/v2/guide/computed.html).

```js
import template from './sw-cms-el-dailymotion.html.twig';
import './sw-cms-el-dailymotion.scss';

Shopware.Component.register('sw-cms-el-dailymotion', {
    template,

    computed: {
        dailyUrl() {
            return `https://www.dailymotion.com/embed/video/${this.element.config.dailyUrl.value}`;
        }
    },    
});
```

The link being used has to follow this pattern: `https://www.dailymotion.com/embed/video/<videoId>`, so the only variable you need from the shop manager
is the video ID. 
That's what you're doing here - you're building the link like mentioned above and you add the value of `dailyUrl` from the config.
This value will be provided by the config component, that you're going to create in the next step.

In order for this to work though, you have to call the method `initElementConfig` from the `cms-element` mixin.
This will take care of dealing with the `configComponent` and therefore providing the configured values.

```js
import template from './sw-cms-el-dailymotion.html.twig';
import './sw-cms-el-dailymotion.scss';

Shopware.Component.register('sw-cms-el-dailymotion', {
    template,

    mixins: [
        'cms-element'
    ],

    computed: {
        dailyUrl() {
            return `https://www.dailymotion.com/embed/video/${this.element.config.dailyUrl.value}`;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('dailymotion');
        }
    }
});
```

Now, the method `initElementConfig` is immediately executed once this component is created.

Time to add the last remaining part of this component: The styles to be applied.
Since Dailymotion takes care of responsive layouts itself, you just have to scale the iFrame to 100% width and 100% height.
Yet, there's a recommended `min-height` of 315px, so add that one as well.

```scss
.sw-cms-el-dailymotion {
    height: 100%;
    width: 100%;
    min-height: 315px;

    .sw-cms-el-dailymotion-iframe-wrapper {
        position: relative;
        padding-bottom: 56.25%;
        height: 0;
        overflow: hidden;

        iframe {
            width: 100%;
            height: 100%;
            position: absolute;
            left: 0;
            top: 0;
            overflow: hidden
        }
    }
}

```

That's it for this component! Import it in your element's `index.js` file.

```js
import './component';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
...
});
```

### The configuration

Let's head over to the last remaining component. Create a directory `config`, an `index.js` file in there and register your config component `sw-cms-el-config-dailymotion`.

```js
import template from './sw-cms-el-config-dailymotion.html.twig';

Shopware.Component.register('sw-cms-el-config-dailymotion', {
    template,

    mixins: [
        'cms-element'
    ],

    computed: {
        dailyUrl() {
            return this.element.config.dailyUrl;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('dailymotion');
        },

        onElementUpdate(element) {
            this.$emit('element-update', element);
        }
    }
});
```

Just like always, it comes with a template, no styles necessary here though. Create the template file now.
Also, the `initElementConfig` method has to be called in here as well, just the same way you've done it in your main component.

A little spoiler: This file will remain like this already, you can close it now.

Open up the template `sw-cms-el-config-dailymotion.html.twig` instead.
What do we need to be displayed in the config?
Just a text element, so the shop manager can apply a Dailymotion video ID.
Quite simple, right?

```twig
{% block sw_cms_element_dailymotion_config %}
    <sw-text-field
          class="swag-dailymotion-field"
          label="Dailymotion video link"
          placeholder="Enter dailymotion link..."
          :value="currentValue"
          @input="beforeGetValue">
    </sw-text-field>
{% endblock %}
```

This would render the `sw-field` component two times. Once as a `text` field, once as a `switch` field. The `v-model` takes care
of binding the field's values to the values from the config. 

Don't forget to include your config in your `index.js`.

```javascript
import './component';
import './config';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
    // ...
});
```

That's it! You could now go ahead and fully test your new element!
Install this plugin via `bin/console plugin:install --activate CustomCmsElement`, rebuild the administration via `./psh.phar administration:build`
and start using your new element in the administration!
Of course, the Storefront implementation is still missing, so your element wouldn't be rendered in the Storefront yet.

#### Side note: swag-dailymotion-field

In the final plugin, whose source you can find at the end of this HowTo, there's a `swag-dailymotion-field` being used instead of the `sw-field` text component for the video ID.
This is **not** necessary, but it comes with a neat feature: It is capable of dealing with both a full video's URL, as well as just the video's ID.
Otherwise, you'd have to explain to the shop manager, how he finds a video's ID. Using the custom component `swag-dailymotion-field`, this will be taken
care of automatically, the shop manager can just copy the full Dailymotion video's URL and paste it into the configuration.

The source for this custom component can be found [here](https://github.com/shopware/swag-docs-custom-cms-element/tree/master/src/Resources/app/administration/src/app/component/form/swag-dailymotion-field).

### Storefront implementation

Just like the CMS blocks, each element's storefront representation is always expected in the directory [platform/src/Storefront/Resources/views/storefront/element](https://github.com/shopware/platform/tree/master/src/Storefront/Resources/views/storefront/element).
In there, a twig template named after your custom element is expected, in this case a file named `cms-element-dailymotion.html.twig`.

So go ahead and re-create that structure in your plugin:
`<plugin root>/src/Resources/views/storefront/element/`

In there create a new twig template named after your element, so `cms-element-dailymotion.html.twig` that is.

The template for this is super easy though, just like it's been in your main component for the administration.
Just add an iFrame again. Simply apply the same styles like in the administration, 100% to both height and width that is.

```twig
{% block element_dailymotion %}
    <div class="cms-element-dailymotion" style="height: 100%; width: 100%">

        {% block element_dailymotion_image_inner %}
            <div class="sw-cms-el-dailymotion">
                <div style="position:relative; padding-bottom:56.25%; height:0; overflow:hidden;">
                    <iframe style="width:100%; height:100%; position:absolute; left:0px; top:0px; overflow:hidden"
                            src="https://www.dailymotion.com/embed/video//{{ element.config.dailyUrl.value }}"
                            frameborder="0"
                            type="text/html"
                            width="100%"
                            height="100%">
                    </iframe>
                </div>
            </div>
        {% endblock %}
    </div>
{% endblock %}

```

The URL is parsed here using the twig variable `element`, which is automatically available in your element's template.

Once more: That's it! Your element is now fully working! The shop manager can choose your new element in the 'Shopping Experiences' module,
he can configure it and even see it being rendered live in the administration.
After saving and applying this layout to e.g. a category, this element will also be rendered into the storefront!

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-custom-cms-element).
