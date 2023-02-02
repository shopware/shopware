[titleEn]: <>(Adding a custom CMS element)
[metaDescriptionEn]: <>(This HowTo will teach you to create a new CMS element via a plugin.)
[hash]: <>(article:how_to_custom_cms_element)

This HowTo will teach you to create a new CMS element via a plugin.

## Setup

You won't learn to create a plugin in this guide, head over to our [Plugin quick start guide](./../2-internals/4-plugins/010-plugin-quick-start.md) to
create your plugin first.
Also, you should know how to handle the "Shopping Experiences" module in the administration first.
Have a look at our documentation about the "Shopping Experiences" module [here](https://docs.shopware.com/en/shopware-6-en/content/ShoppingExperiences).
Nothing else, but these two requirements, is necessary to get started on this subject.

The plugin in this example will be named `CustomCmsElement`.

## Custom element

Imagine you'd want to create a new element to display a YouTube video.
The shop manager can configure the link of the video to be shown and maybe add some more configurations, such as 'Show controls', which
hides / shows the control elements of a YouTube video.

That's exactly what you're going to build in this HowTo.

Creating a new element requires you to extend the administration.

### Injecting into the administration
    
The main entry point to customize the administration via plugin is the `main.js` file.
It has to be placed into a `<plugin root>/src/Resources/app/administration/src` directory in order to be automatically found by the Shopware
platform.

Create this `main.js` file for now, it will be used later.

### Registering a new element

Your plugin's structure should always match the core's structure. When thinking about creating a new element, you should
recreate the directory structure of core elements in your plugin.
Thus, recreate [this structure](https://github.com/shopware/platform/tree/master/src/Administration/Resources/app/administration/src/module/sw-cms/elements) in your plugin:
`<plugin root>/src/Resources/app/administration/src/module/sw-cms/elements`

In there you create a directory for each new element you want to create, in this example a directory `youtube` is created.

Now create a new file `index.js` inside the `youtube` directory, since it will be automatically loaded
when importing this element in your `main.js`.
Speaking of that, right after having created the `index.js` file, you can actually import your new element's directory in
the `main.js` file already:

```js
import './module/sw-cms/elements/youtube';
```

Now open up your empty `index.js` file. In order to register a new element to the system, you have to call the method `registerCmsElement`
of the [cmsService](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/module/sw-cms/service/cms.service.js).
Since it's available in the Dependency Injection Container, you can fetch it from there.

First of all, access our `Applicaton` wrapper, which will grant you access to the DI container. This `Application` wrapper has access to the DI container, so go ahead and fetch the `cmsService` from it and call the
mentioned `registerCmsElement` method.

```js
Shopware.Service('cmsService').registerCmsElement();
```

The method `registerCmsElement` takes a configuration object, containing the following necessary data:
name
 : The technical name of your element. Will be used for the template loading later on.
 
label
 : A name to be shown for your element in the User Interface.
 
component
 : The Vue component to be used when rendering your actual element in the administration.
 
configComponent
 : The Vue component defining the "configuration detail" page of your element.
 
previewComponent
 : The Vue component to be used in the "list of available elements". Just shows a tiny preview of what your element
  would look like if it was used.
  
defaultConfig
: A default configuration to be applied to this element. Must be an object containing those default values.


Go ahead and create this configuration object yourself.
Here's what it should look like after having set all of those options:
```js
Shopware.Service('cmsService').registerCmsElement({
    name: 'youtube',
    label: 'sw-cms.elements.customYouTubeElement.label',
    component: 'sw-cms-el-youtube',
    configComponent: 'sw-cms-el-config-youtube',
    previewComponent: 'sw-cms-el-preview-youtube',
    defaultConfig: {
        videoSrc: {
            source: 'static',
            value: 'Y4mGIZZL8jA'
        },
        showControls: {
            source: 'static',
            value: true
        }
    }
});
```

The property `name` does not require further explanation.
But you need to create a snippet files in you plugin directory for the `label` property.

To do this, create a folder with the name `snippet` in your `sw-cms` folder. After that create the files for the languages. For example `de-DE.json` and `en-GB.json`.

The content of your snippet file should look something like this:

```json
{
  "sw-cms": {
    "elements": {
       "customYouTubeElement": {
        "label": "YouTube Video"
      }
    }
  }
}
```

Next, import the snippet files into your `main.js`.

```js
import './module/sw-cms/elements/youtube';
import deDE from './module/sw-cms/snippet/de-DE.json';
import enGB from './module/sw-cms/snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
```

You've now finished the part for the snippets. For more information about snippets, [click here](https://docs.shopware.com/en/shopware-platform-dev-en/how-to/adding-snippets).

For all three fields `component`, `configComponent` and `previewComponent`, components that do not **yet** exist were applied. Those will be created
in the next few steps as well.
The `defaultConfig` defines the default values for the element's configurations. There will be a text field to enter a YouTube video's ID, `videoSrc`, and a
toggle to en-/disable the option to show the control elements in the YouTube video, `showControls`.
By default, it will now use the ID of a Shopware video and the controls will be shown.

Now you have to create the three missing components, let's start with the preview component.

### Preview

Create a new directory `preview` in your element's directory `youtube`. In there, create a new file `index.js`, just like for all components.
Then register your component, using the `Component` wrapper.
This HowTo will not explain how a custom component can be created though, so head over to the official HowTo about [creating a custom component](./280-custom-component.md)
to learn this first.

```js
import template from './sw-cms-el-preview-youtube.html.twig';
import './sw-cms-el-preview-youtube.scss';

Shopware.Component.register('sw-cms-el-preview-youtube', {
    template
});
```

Just like most components, it has a custom template and also some styles.
Focus on the template first, create a new file `sw-cms-el-preview-youtube.html.twig`.

So what do you want to show here? Maybe the default 'mountain' preview image, that's already being used for the `image` element.
And on top of that, you could place our icon `multicolor-action-play`. Head over to your [icon library](https://component-library.shopware.com/#/icons/) to find this icon.

That means: You'll need a container to contain both the image and the icon.
In there, you create an `img` tag and use the [sw-icon component](https://component-library.shopware.com/#/components/sw-icon) to display the icon.

```twig
{% block sw_cms_element_youtube_preview %}
    <div class="sw-cms-el-preview-youtube">
        <img class="sw-cms-el-preview-youtube-img" :src="'/administration/static/img/cms/preview_mountain_small.jpg' | asset">

        <sw-icon class="sw-cms-el-preview-youtube-play" name="multicolor-action-play"></sw-icon>
    </div>
{% endblock %}
```

The icon would now be displayed beneath the image, so let's add some styles for this by creating the file `sw-cms-el-preview-youtube.scss`.

The container needs to have a `position: relative;` style. This is necessary, so the child `sw-icon` can be positioned absolute and will do so
relative to the container's position.
Thus, the icon receives a `position: absolute;` style, plus some `top` and `left` values to center it.

```scss
.sw-cms-el-preview-youtube {
    position: relative;

    .sw-cms-el-preview-youtube-play {
        $icon-height: 80px;
        $icon-width: $icon-height;
        position: absolute;
        height: $icon-height;
        width: $icon-width;

        left: calc(50% - #{$icon-width/2});
        top: calc(50% - #{$icon-height/2});
    }
}
```

The centered positioning is realised by using 50% on `top` and `left`. Since that would be 50% from the upper left corner of the icon, this wouldn't really center
the icon yet. Subtract the half of the icon's width and height and then you're fine.

Two more things missing here.
First of all, the image itself has no styling yet. Just make it scale to 100% of its parent's size.

```scss
.sw-cms-el-preview-youtube {
    position: relative;
    
    .sw-cms-el-preview-youtube-img {
        margin: 0;
        display: block;
        max-width: 100%;
    }
    ...
}
```

Second, the icon is grey by default. Since our `sw-icon` component will render an `.svg`, you can apply styles to the elements.
In this case, it's mainly a `circle` element. Use the YouTube red for the fill color here: `#FF0000`

This is what your preview's final `.scss` should look like now:
```scss
.sw-cms-el-preview-youtube {
    position: relative;

    .sw-cms-el-preview-youtube-img {
        margin: 0;
        display: block;
        max-width: 100%;
    }

    .sw-cms-el-preview-youtube-play {
        $icon-height: 80px;
        $icon-width: $icon-height;
        position: absolute;
        height: $icon-height;
        width: $icon-width;

        left: calc(50% - #{$icon-width/2});
        top: calc(50% - #{$icon-height/2});

        circle {
            fill: #FF0000;
            opacity: 0.9;
        }
    }
}
```

One last thing: Import your preview component in your element's `index.js` file, so it's loaded.

```js
import './preview';

Shopware.Service('cmsService').registerCmsElement({
...
}
```

### Rendering the element

The next would be the main component `sw-cms-el-youtube`, the one to be rendered when the shop manager actually decided to use your element by clicking
on the preview.
Thus, you want to show the actually configured video here now.
Start with the basic again, create a new directory `component`, in there a new file `index.js` and then register your component `sw-cms-el-youtube`.

```js
import template from './sw-cms-el-youtube.html.twig';
import './sw-cms-el-youtube.scss';

Shopware.Component.register('sw-cms-el-youtube', {
    template
});
```

Also create the template file `sw-cms-el-youtube.html.twig` and the `.scss` file `sw-cms-el-youtube.scss`.

The template doesn't have to include a lot. Having a look at how YouTube video embedding works, you just have to add an `iframe`
with an `src` attribute pointing to the video.

```twig
{% block sw_cms_element_youtube %}
    <div class="sw-cms-el-youtube">
        <iframe :src="videoSrc"></iframe>
    </div>
{% endblock %}
```

You can't just use a static `src` here, since the shop manager wants to configure the video he wants to show. Thus, we're fetching
that link via VueJS now.

Let's add the code to provide the `src` for the iframe. For this case you're going to use a [computed property](https://vuejs.org/v2/guide/computed.html) of VueJS.

```js
import template from './sw-cms-el-youtube.html.twig';
import './sw-cms-el-youtube.scss';

Shopware.Component.register('sw-cms-el-youtube', {
    template,

    computed: {
        videoSrc() {
            return 'https://www.youtube.com/embed/' + this.element.config.videoSrc.value;
        }
    }
});
```

The link being used has to follow this pattern: `https://www.youtube.com/embed/<videoId>`, so the only variable you need from the shop manager
is the video ID. 
And that's what you're doing here - you're building the link like mentioned above and you add the value of `videoSrc` from the config.
This value will be provided by the config component, that you're going to create in the next step.

In order for this to work though, you have to call the method `initElementConfig` from the `cms-element` mixin.
This will take care of dealing with the `configComponent` and thus providing the configured values.

```js
import template from './sw-cms-el-youtube.html.twig';
import './sw-cms-el-youtube.scss';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-youtube', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    computed: {
        videoSrc() {
            return 'https://www.youtube.com/embed/' + this.element.config.videoSrc.value;
        }
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('youtube');
        },
    }
});
```

Now the method `initElementConfig` is immediately executed once this component was created.

Also, you could already add the `showControls` config here as well.
The `showControls` config has to be applied to the YouTube link itself, by adding another GET parameter `controls`, so 
adjust the link in the `videoSrc` computed property.

```js
computed: {
     videoSrc() {
        return 'https://www.youtube.com/embed/'
            + this.element.config.videoSrc.value
            + '?controls='
            + (this.element.config.showControls.value ? 1 : 0);
    }
},
```

If the configuration of `showControls` is set to `true`, set the GET value to 1, otherwise 0.

Time to add the last remaining part of this component. The styles to be applied.
Since YouTube takes of responsive layouts itself, you just have to scale the iFrame to 100% width and 100% height.
Yet, there's a recommended `min-height` of 315px, so add that one as well.

```scss
.sw-cms-el-youtube {
    height: 100%;
    width: 100%;

    min-height: 315px;

    iframe {
        height: 100%;
        width: 100%;
    }
}
```

That's it for this component! Import it in your element's `index.js` file.

```js
import './component';
import './preview';

Shopware.Service('cmsService').registerCmsElement({
...
}
```

### The configuration

Let's head over to the last remaining component. Create a directory `config`, an `index.js` file in there and register your config component `sw-cms-el-config-youtube`.

```js
import template from './sw-cms-el-config-youtube.html.twig';

const { Component, Mixin } = Shopware;

Component.register('sw-cms-el-config-youtube', {
    template,

    mixins: [
        Mixin.getByName('cms-element')
    ],

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.initElementConfig('youtube');
        }
    }
});
```

Just like always, it comes with an template, no styles necessary here though. Create the template file now.
Also, the `initElementConfig` method has to be called in here as well, just the same way like you've done in your main component.

A little spoiler: This file will remain like this already, you can close it now.

Open up the template `sw-cms-el-config-youtube.html.twig` instead.
What do we need to be displayed in the config?
Just a text element, so the shop manager can apply a YouTube video ID, and a toggle to en-/disable the controls.
Quite simple, right?

```twig
{% block sw_cms_element_image_config %}
<div class="sw-cms-el-config-youtube">
    <sw-field class="swag-youtube-field"
          v-model="element.config.videoSrc.value"
          type="text"
          label="YouTube Video ID"
          placeholder="Enter ID...">
    </sw-field>
    <sw-field class="sw-cms-el-config-youtube__show-controls"
          v-model="element.config.showControls.value"
          type="switch"
          label="Show video controls">
    </sw-field>
</div>
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
...
}
```

That's it! You could now go ahead and fully test your new element!
Install this plugin via `bin/console plugin:install --activate CustomCmsElement`, rebuild the administration via `./psh.phar administration:build`
and start using your new element in the administration!
Of course, the Storefront implementation is still missing, so your element wouldn't be rendered in the Storefront yet.

#### Sidenote: swag-youtube-field

In the final plugin, whose source you can find at the end of this HowTo, there's a `swag-youtube-field` being used instead of the `sw-field` text component for the video ID.
This is **not** necessary, but it comes with a neat feature: It is capable of dealing with both a full video's URL, as well as just the video's ID.
Otherwise you'd have to explain to the shop manager, how he finds a video's ID. Using the custom component `swag-youtube-field`, this will be taken
care of automatically, the shop manager can just copy the full YouTube video's URL and paste it into the configuration.

The source for this custom component can be found [here](https://github.com/shopware/swag-docs-custom-cms-element/tree/master/src/Resources/app/administration/src/app/component/form/swag-youtube-field).

### Storefront implementation

Just like the CMS blocks, each element's storefront representation is always expected in the directory [platform/src/Storefront/Resources/views/storefront/element](https://github.com/shopware/platform/tree/master/src/Storefront/Resources/views/storefront/element).
In there, a twig template named after your custom element is expected, in this case a file named `cms-element-youtube.html.twig` is expected.

So go ahead and re-create that structure in your plugin:
`<plugin root>/src/Resources/views/storefront/element/`

In there create a new twig template named after your element, so `cms-element-youtube.html.twig` that is.

The template for this is super easy though, just like it's been in your main component for the administration.
Just add an iFrame again. Unfortunately, styles have to be applied using inline-styles as of now.
This is to be changed and updated in the next few days, just stay tuned.
Simply apply the same styles like in the administration, 100% to both height and width that is.

```twig
{% block element_youtube %}
    <div class="cms-element-youtube" style="height: 100%; width: 100%">
        {% block element_image_inner %}

            <iframe style="min-height:315px; height: 100%; width: 100%;"
                src="https://www.youtube.com/embed/{{ element.config.videoSrc.value }}?controls={{ element.config.showControls.value|number_format }}"
                allowfullscreen>
            </iframe>
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
