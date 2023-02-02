[titleEn]: <>(Adding a custom CMS block)
[metaDescriptionEn]: <>(This HowTo will teach you to create a new CMS block via a plugin.)
[hash]: <>(article:how_to_custom_cms_block)

This HowTo will teach you to create a new CMS block via a plugin.

## Setup

You won't learn to create a plugin in this guide, head over to our [developer guide](./../20-developer-guide/10-plugin-base.md) to
create your plugin first.
Also, you should know how to handle the "Shopping Experiences" module in the administration first.
Have a look at our documentation about the "Shopping Experiences" module [here](https://docs.shopware.com/en/shopware-6-en/content/ShoppingExperiences).
Nothing else, but these two requirements, is necessary to get started on this subject.

The plugin in this example will be named `CustomCmsBlock`.

## Custom block

Let's get started with adding your first custom block. By default, Shopware 6 comes with several
blocks, such as a block called "image_text".
It renders an image element on the left side and a simple text element on the right side.
For this HowTo, you're going to create a new block to swap those two elements, so the text is on the left side
and the image on the right side.

All blocks can be found in the directory [<platform root>/src/Administration/Resources/app/administration/src/module/sw-cms/blocks](https://github.com/shopware/platform/tree/master/src/Administration/Resources/app/administration/src/module/sw-cms/blocks).
In there, they are divided into the categories `text`, `text-image`, `image` and `commerce`.

`text`
 : Blocks only using text elements are located here.
 
`text-image`
 : Blocks, that are making use of both, text and images, belong here.
 
`image`
 : Only image elements are used by these blocks.
 
`commerce`
 : Blocks using a special template can be found here, e.g. a product slider block.
 
Creating a new block means adjusting the administration with your plugin, and that's what you're going to do first.

### Injecting into the administration
    
The main entry point to customize the administration via plugin is the `main.js` file.
It has to be placed into a `<plugin root>/src/Resources/app/administration/src` directory in order to be automatically found by the Shopware
platform.

Create this `main.js` file for now, it will be used later.

### Registering a new block

Your plugin's structure should always match the core's structure. When thinking about creating a new block, you should
recreate the directory structure of core blocks in your plugin.
The block, which you're going to create, consists of an `image` and a `text` element, so it belongs to the category `text-image`.
Thus, create the directory `<plugin root>/src/Resources/app/administration/src/module/sw-cms/blocks/text-image`.

In there, you have to create a new directory for each block you want to create, the directory's name representing
the block's name. For this example, the name `image-text-reversed` is going to be used, so create this directory in there.

Now create a new file `index.js` inside the `image-text-reversed` directory, since it will be automatically loaded
when importing this block in your `main.js`.
Speaking of that, right after having created the `index.js` file, you can actually import your new block directory in
the `main.js` file already:

```js
import './module/sw-cms/blocks/text-image/image-text-reversed';
```

Back to your `index.js`, which is still empty.
In order to register a new block, you have to call the `registerCmsBlock` method of the [cmsService](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/module/sw-cms/service/cms.service.js).
Since it's available in the Dependency Injection Container, you can fetch it from there.

First of all, access our `Applicaton` wrapper, which will grant you access to the DI container. This `Application` wrapper has access to the DI container, so go ahead and fetch the `cmsService` from it and call the
mentioned `registerCmsBlock` method.

```js
Shopware.Service('cmsService').registerCmsBlock();
```

The method `registerCmsBlock` takes a configuration object, containing the following necessary data:

name
 : The technical name of your block. Will be used for the template loading later on.
 
label
 : A name to be shown for your block in the User Interface.
 
category
 : The category this block belongs to.
 
component
 : The Vue component to be used when rendering your actual block in the administration.
 
previewComponent
 : The Vue component to be used in the "list of available blocks". Just shows a tiny preview of what your block
   would look like if it was used.
 
defaultConfig
 : A default configuration to be applied to this block. Must be an object containing those default values.
 
slots
 : Key-Value pair to configure which element to be shown in which slot. Will be explained in the next few steps
   when creating a template for this block.

Go ahead and create this configuration object yourself.
Here's what it should look like after having set all of those options:
```js
Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-text-reversed',
    label: 'sw-cms.blocks.textImage.imageTextReversed.label',
    category: 'text-image',
    component: 'sw-cms-block-image-text-reversed',
    previewComponent: 'sw-cms-preview-image-text-reversed',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        left: 'text',
        right: 'image'
    }
});
```

The properties `name` and `category` do not require further explanation.
But you need to create a snippet files in you plugin directory for the `label` property.

To do this, create a folder with the name `snippet` in your `sw-cms` folder. After that create the files for the languages. For example `de-DE.json` and `en-GB.json`.

The content of your snippet file should look something like this:

```json
{
  "sw-cms": {
    "blocks": {
      "imageText": {
        "imageTextReversed": {
          "label": "YouTube Video"
        }
      }
    }
  }
}
```

Next, import the snippet files into your `main.js`.

```js
import './module/sw-cms/blocks/text-image/image-text-reversed';
import deDE from './module/sw-cms/snippet/de-DE.json';
import enGB from './module/sw-cms/snippet/en-GB.json';

Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);
```

You've now finished the part for the snippets. For more information about snippets, [click here](https://docs.shopware.com/en/shopware-platform-dev-en/how-to/adding-snippets).

For both fields `component` and `previewComponent`, components that do not **yet** exist were applied. Those will be created
in the next few steps as well.
The `defaultConfig` just gets some minor margins and the sizing mode 'boxed', which will result in a CSS class [is--boxed](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/module/sw-cms/component/sw-cms-block/sw-cms-block.scss#L22) being applied
to that block later.

The slots are defined by an object, where the key represents the slot's name and the value being the technical name
of the element to be used in this slot. This will be easier to understand when having a look at the respective template in a few minutes.
Instead of using the technical name of an element, you could also apply another configuration to a slot.

This is not necessary for this HowTo, but here's an example of what this would look like:
```js
slots: {
    'name-of-the-slot': {
        type: 'name-of-the-element-to-be-used',
        default: {
            config: {
                displayMode: { source: 'static', value: 'cover' }
            },
            data: {
                media: {
                    url: '/administration/static/img/cms/preview_camera_large.jpg'
                }
            }
        }
    }
}
```

In the next step, you're going to create the component for the actual representation of the block in the administration.

### Rendering the block

You've set the `component` to be used when rendering your block to be 'sw-cms-block-image-text-reversed'.
This component does not exist yet, so let's create this one real quick.
This HowTo will not explain how a custom component can be created though, so head over to the official HowTo about [creating a custom component](./280-custom-component.md)
to learn this first.

First of all, create a new directory `component` in your block's directory. In there, create a new `index.js` file
and register your custom component `sw-cms-block-image-text-reversed`.

```js
import template from './sw-cms-block-image-text-reversed.html.twig';
import './sw-cms-block-image-text-reversed.scss';

Shopware.Component.register('sw-cms-block-image-text-reversed', {
    template
});
```

Just like most components, it has a custom template and also some styles.
Focus on the template first, create a new file `sw-cms-block-image-text-reversed.html.twig`.

This template now has to define the basic structure of your custom block. In this simple case, you only need a
parent container and two sub-elements, whatever those are. 
That's also were the slots come into play: You've used two slots in your block's configuration, `left` and `right`.
Make sure to create those slots in the template as well now.

```twig
{% block sw_cms_block_image_text_reversed %}
    <div class="sw-cms-block-image-text-reversed">
        <slot name="left">{% block sw_cms_block_image_text_reversed_slot_left %}{% endblock %}</slot>
        <slot name="right">{% block sw_cms_block_image_text_reversed_slot_right %}{% endblock %}</slot>
    </div>
{% endblock %}
```

You've got a parent `div` containing the two required [slots](https://vuejs.org/v2/guide/components-slots.html). If you were to rename the first slot `left` to something else,
you'd have to adjust this in your block's configuration as well.

Those slots would be rendered from top to bottom now, instead of from left to right.
That's why your block comes with a custom `.scss` file, create it now.
In there, simply use a grid here to display your elements next to each other.
You've set a CSS class for your block, which is the same as its name.

```scss
.sw-cms-block-image-text-reversed {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    grid-gap: 40px;
}
```

That's it for this component! Make sure to import your `component` directory in your `index.js` file, so your
new component actually gets loaded.

```js
import './component';

Shopware.Service('cmsService').registerCmsBlock({
    ...
});
```

Your block can now be rendered in the designer. Let's continue with the preview component.

### Block preview

You've also set a property `previewComponent` containing the value `sw-cms-preview-image-text-reversed`.
Time to create this component as well. For this purpose, stick to the core structure again and create a new directory
`preview`.
In there, again, create an `index.js` file, register your component by its name and load a template and a `.scss` file.

```js
import template from './sw-cms-preview-image-text-reversed.html.twig';
import './sw-cms-preview-image-text-reversed.scss';

Shopware.Component.register('sw-cms-preview-image-text-reversed', {
    template
});
```

The preview element doesn't have to deal with mobile viewports or anything alike, it's just a simplified preview
of your block.
Thus, simply create a template containing a text and an image and use the styles to place them next to each other.

The template:
```twig
{% block sw_cms_block_image_text_reversed_preview %}
    <div class="sw-cms-preview-image-text-reversed">
        <div>
            <h2>Lorem ipsum dolor</h2>
            <p>Lorem ipsum dolor sit amet, consetetur sadipscing elitr.</p>
        </div>
        <img :src="'/administration/static/img/cms/preview_mountain_small.jpg' | asset">
    </div>
{% endblock %}
```

Just a div containing some text and an example image next to that.
For the styles, you can simply use the grid property of CSS again. Since you don't have to care about mobile
viewports, this is even easier this time.

```scss
.sw-cms-preview-image-text-reversed {
    display: grid;
    grid-template-columns: 1fr 1fr;
    grid-column-gap: 20px;
    padding: 15px;
}
```

A two-column, some padding and spacing here and there, done.

Now, import this component in your block's `index.js` as well.
This is, what your final block's `index.js` file should look like now:

```js
import './component';
import './preview';

Shopware.Service('cmsService').registerCmsBlock({
    name: 'image-text-reversed',
    label: 'Text next to image',
    category: 'text-image',
    component: 'sw-cms-block-image-text-reversed',
    previewComponent: 'sw-cms-preview-image-text-reversed',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: '20px',
        marginRight: '20px',
        sizingMode: 'boxed'
    },
    slots: {
        left: 'text',
        right: 'image'
    }
});
```

Now go ahead, install your plugin via `./psh.phar plugin:install --activate CustomCMSBlock` and re-build the administration via
`./psh.phar administration:build`.

You should now be able to use your new block in the "Shopping Experiences" module.

## Storefront representation

While your new block is fully functional in the administration already, you've never defined a template for it for the Storefront.

A block's storefront representation is always expected in the directory [platform/src/Storefront/Resources/views/storefront/block](https://github.com/shopware/platform/tree/master/src/Storefront/Resources/views/block).
In there, a twig template named after your block is expected.

So go ahead and re-create that structure in your plugin:
`<plugin root>/src/Resources/views/storefront/block/`

In there create a new twig template named after your block, so `cms-block-image-text-reversed.html.twig` that is.

Since the [original 'image_text' file](https://github.com/shopware/platform/blob/master/src/Storefront/Resources/views/storefront/block/cms-block-image-text.html.twig) is already perfectly fine, you can simply extend from it
in your storefront template.

```twig
{% sw_extends '@Storefront/storefront/block/cms-block-image-text.html.twig' %}
```

And that's it for the Storefront as well in this example!
Make sure to have a look at the other original templates to get an understand of how the templating for blocks works.

Following up, you might want to have a look at our HowTo on [creating a custom element](./300-custom-cms-element.md).

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-custom-cms-block).
