[titleEn]: <>(Step 8: Extending the product detail page)
[hash]: <>(article:bundle_extension)

Time to show your Bundle where it really counts: On the detail page in the Storefront!

## Loading the association

When the product detail page in the Storefront is opened, a product is loaded by a `Criteria` object, which also contains the associations to be loaded.
Those are for example associations like the product's prices or the associated media for the product.
Yet, your bundles are not part of those "to be loaded" associations, because this association was never requested on the `Criteria` instance.

Thus, you have to somehow manipulate the `Criteria` instance before it is used for a search. There's an event for this case, which is `product-detail.page.criteria`.
Don't use it like this though, rather use the constant from the respective `Event` class: `\Shopware\Storefront\Page\Product\ProductPageCriteriaEvent::class`

But how do you handle events in the first place?
This is done by using the [Symfony event subscriber](https://symfony.com/doc/current/event_dispatcher.html#creating-an-event-subscriber).
Create a new directory like this: `<plugin root>/src/Storefront/Page/Product/Subscriber` and in there a new file called `ProductPageCriteriaSubscriber.php`.
The path to this subscriber is relative to the core's path to the product related content. Just head over to `<platform root>/src/Storefront/Page/Product` to see
the core structure here.

A subscriber should implement the `\Symfony\Component\EventDispatcher\EventSubscriberInterface` interface and thus has to implement the method `getSubscribedEvents`,
which returns an array of events to listen to and their respective method to be executed then.
Just use the constant mentioned above and choose a method name to call once the event is triggered. In this example `onProductCriteriaLoaded` is used.

```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Storefront\Page\Product\Subscriber;

use Shopware\Storefront\Page\Product\ProductPageCriteriaEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ProductPageCriteriaSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            ProductPageCriteriaEvent::class => 'onProductCriteriaLoaded'
        ];
    }

    public function onProductCriteriaLoaded(ProductPageCriteriaEvent $event): void
    {
    }
}
```

This example also already knows the method `onProductCriteriaLoaded`. Each event comes with its own event parameter, `ProductPageCriteriaEvent` in this case.
It grants access to the criteria object before it has been used for a search, so time to add your new association in there.

```php
public function onProductCriteriaLoaded(ProductPageCriteriaEvent $event): void
{
    $event->getCriteria()->addAssociation('bundles.products.cover');
}
```

So, first of all you're telling the criteria to also load a set of nested associations.
First we want to add the `bundles` association, that you defined earlier in this tutorial series.
You can find this in the `<plugin root>/src/Core/Content/Product/ProductExtension.php` file, make sure to have a look at it again to remember what you've done there.

Imagine being on the detail page, where only the `product` entity is loaded. You now want to show "Hey, this product has a bundle", so you have to add the `bundle` association.
This association call then adds the basic bundle entity as an association, but not the bundle's own associations. All you would know then is the `name`, the `discount` and the `discountType` of the bundle.
Now, you also want to show **all products** that are part of this specific bundle, but you don't have this information yet. 
Thus, you're also adding the `products` of each bundle to the `associationPath` that should be loaded.
Because we want to display all products in the bundle in its own buy-box we also need to add the `cover`-Association to our `associationPath`, so that the product images can be displayed properly.
Now you'll have:
- The product entity
- The bundle entity related to this product
- The product entities being assigned to this bundle
- The cover image of each product assigned to this bundle

Hopefully it's clear why this is necessary now.

Last thing missing is to register your custom subscriber to Shopware 6, so it even gets considered. Open your plugin's `services.xml` file for it.
You'll have to define your subscriber as a service using the `kernel.event_subscriber` tag:
```xml
<services>
    ...
    <service id="Swag\BundleExample\Storefront\Page\Product\Subscriber\ProductPageCriteriaSubscriber">
        <tag name="kernel.event_subscriber"/>
    </service>
</services>
```

## Editing the detail template

By default, Shopware 6 is looking for a directory called `Resources/views` relative to your plugin's base class.
Its default location can be changed by overriding your plugin's base class [getViewPaths](./../../60-references-internals/40-plugins/020-plugin-base-class.md#getViewPaths) method.
This directory is then considered by the Twig FileSystemLoader, so all templates in this directory will be loaded automatically.
In this example, the path therefore would be: `<plugin root>/src/Resources/views` - guess you know what to do now, create this directory.

### Adding a badge

The first thing you want to do, is to show a neat `Bundle` badge on top of the detail page. There's several steps necessary now:
1. Figuring out which template and which block contains the name
2. Recreating the directory path to the original file in your own plugin
3. Creating your own template and make it extend from the original one
4. Override the block with your own content
5. Using the new bundle association to only show a badge, where necessary

Let's got through this step by step.
Figuring out the proper template can be quite easy, if you know how to deal with a browser's DevTools. How to handle the DevTools is not explained here!
So head over to a detail page, open the DevTools and choose the [inspection tool](https://developers.google.com/web/tools/chrome-devtools/inspect-styles/) and then
select the name of a product. This will highlight an element, whose class is `product-detail-name`. It's the child of another element with the class
`product-detail`, which sounds perfectly right.
Now copy this class and search for it in the [views directory](https://github.com/shopware/storefront/tree/master/Resources/views) of the Storefront bundle,
which can be found here: `<platform root>/src/Storefront/Resources/views`
Search for the css class `product-detail` in this directory with the tool of your choice, an IDE like PHPStorm is recommended.
You'll find a few results, but only look for the element, which only contains this specific class and nothing else.
This way you'll eventually find the file `<platform root>/src/Storefront/Resources/views/storefront/page/product-detail/index.html.twig`, which creates the main container
for the detail page. That's it, you've found the right file and now know the path to it.

Time for the next step, recreate this path in your plugin's `views` directory, so it looks like this:
`<plugin root>/src/Resources/views/storefront/page/product-detail/index.html.twig`

You've already got the template file, now make it extend from the original one.
This is done using our custom Twig parser `sw_extends` to extend from the default file. It will also need the path to the original file.
```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}
```

The `@Storefront` part points to the `views` directory of the Storefront bundle, the rest of the path should be known to you already.
Now you can add override the original template's blocks by simply adding them in your template.

Having another look at the original file, you'll see the `div` with the class `product-detail` is inside the Twig block `page_product_detail_content`.
This is also the block you're going to override now.
Add this block to your own template.

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail_content %}
    <h2>Hello world</h2>
{% endblock %}
```

This would now completely replace the whole detail content with an 'Hello world' text. Since you don't want the original content to be lost,
you can add the parent's content as well using `{{ parent() }}`:

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail_content %}
    <h2>Hello world</h2>
    
    {{ parent() }}
{% endblock %}
```

This would now render your 'Hello world' above the original content. Time to replace the 'Hello world' with the actual badge.
For this purpose, there's a class called `badge` to render a `div` like a badge. Additional to that, you can use `badge-primary` to highlight it even more.

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% block page_product_detail_content %}
    <div class="badge badge-primary">Bundle</div>
    
    {{ parent() }}
{% endblock %}
```

One more thing is missing though. This badge would now be shown on **every** detail page, yet you only want to show it for products, that actually
have a least one bundle. The page templates also come with a `page` twig variable, which contains the full product entity, so you can access it and check if
there's a bundle association on the product entity.

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{# @var page \Shopware\Storefront\Page\Product\ProductPage #}
{% block page_product_detail_content %}
    {% if page.product.extension('bundles').elements|length > 0 %}
        <div class="badge badge-primary">Bundle</div>
    {% endif %}

    {{ parent() }}
{% endblock %}
```

You're accessing the `product` entity from the `page` variable now. From there on, you're searching for the `bundles` association in the product's extensions
(remember your `ProductExtension` class?) and then, if there's more than zero bundle elements on this product, you're showing the badge.

Now you should see a badge on every product detail page, whose product is part of at least a single bundle.

### Adding the bundle itself

More important than just a badge, is to show the available bundle themselves. You could just render them above the products description,
but in case of multiple bundles, this would totally bloat the product detail page.
You'd rather want them to be in a separate tab, next to the description.

Each tab consists of two elements, one for showing the tab and the actual tab content.
Go ahead and use the inspection tool of your browser's DevTools and inspect the 'Description' tab on the detail page. You'll find an element with the ID `description-tab`.
When searching for this ID, you'll eventually find the file `<platform root>/src/Storefront/Resources/views/storefront/page/product-detail/tabs.html.twig`, which is the proper file to extend.
Thankfully, it's in the same directory as the `index.html.twig`, so you've already got the equal directory structure in your plugin.
Now create the file `tabs.html.twig` in your plugin and extend from the original file, just like you did before.

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/tabs.html.twig' %}
```

In the original file, you'll find two main parts: One for listing all available tabs as `li` elements inside an `ul` element, and one for providing the tab's content.

Start with adding your own tab by overriding the block `page_product_detail_tabs_navigation_description`, so you can add your own `li` tag to the `ul`.
Copy the original file's tab content as well and replace every occurrence of "description" with "bundle" and replace `{{ "detail.tabsDescription"|trans }}` with `Bundles` for now.

Also make sure to add the `{{ parent() }}` to not just override the description tab, but to add a new tab instead.
This time you want the original content to be loaded first and your new tab afterwards.

It should now look like this:
```twig
{% sw_extends '@Storefront/storefront/page/product-detail/tabs.html.twig' %}

{% block page_product_detail_tabs_navigation_description %}
    {{ parent() }}
    {% if page.product.extension('bundles').elements|length > 0 %}
        <li class="nav-item">
            <a class="nav-link"
               id="bundle-tab"
               data-toggle="tab"
               data-offcanvas-tab="true"
               href="#bundle-tab-pane"
               role="tab"
               aria-controls="bundle-tab-pane"
               aria-selected="false">
                <span>Bundles</span>
            </a>
        </li>
    {% endif %}
{% endblock %}
```

Let's go through this real quick:
First, there's the same condition, checking for any bundles to be present at all.
There's a new `li` element and in there an `a` element, which is necessary because the `href` link to the tab's content is needed.
We will neither explaining the classes and IDs here, they'll simply be used in the next step and bring some necessary styles, neither will the `ARIA` attributes be explained, you can just look them up.
Only make sure to set the `aria-selected` attribute to false here.
Instead, the really important attributes are `data-toggle`, `data-offcanvas-tab` and `href`.

- `data-toggle="tab"`: Necessary for the [Bootstrap JS Tab](https://www.w3schools.com/bootstrap/bootstrap_ref_js_tab.asp) to work. Without this, your tab wouldn't be clickable and thus the content wouldn't be rendered when clicking it.
- `data-offcanvas-tab`: Opens the tab's content in the off canvas menu when using a smaller viewport
- `href`: Links to the element, which contains the tab's contents and thus must be rendered

Inside the `a` tag, you'll find the tab's label, 'Bundles' in this case. Translations will be added later, don't worry about this yet.

This would already render a bundle tab, but it has no content to show yet. Looking into the original `tabs.html.twig` file, there's a `div` container
for the several tab content's. Copy the first block inside this block, which should be `page_product_detail_tabs_content_description`, and override it in your custom
`tabs.html.twig`.
Once again, you want the original content to come first and then append your bundle tab's content. Also, you only want your bundle tab's content to be available, if there's bundles
at all, so add this condition again as well.
Also, copy the original HTML in here again to manipulate it to your needs afterwards.

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/tabs.html.twig' %}

{% block page_product_detail_tabs_navigation_description %}
    ...
{% endblock %}

{% block page_product_detail_tabs_content_description %}
    {{ parent() }}
    {% if page.product.extension('bundles').elements|length > 0 %}
        <div class="tab-pane fade"
             id="description-tab-pane"
             role="tabpanel"
             aria-labelledby="description-tab">
            {% sw_include '@Storefront/storefront/page/product-detail/description.html.twig' %}
        </div>
    {% endif %}
{% endblock %}
```

Once again, replace every occurrence of 'description' and replace it with 'bundle'.
Also, remove the `active`  and `show` classes, because otherwise your tab's content would immediately be displayed, together with the description tab. You don't want that.
Replace the 'description' in the elements ID, the same for the `aria-labelledby` attribute.
The div's content can also be emptied, you're going to add your own template here now.

```twig
{% block page_product_detail_tabs_content_description %}
    {{ parent() }}
    {% if page.product.extension('bundles').elements|length > 0 %}
        <div class="tab-pane fade"
             id="bundle-tab-pane"
             role="tabpanel"
             aria-labelledby="bundle-tab">
             // Content goes here next
        </div>
    {% endif %}
{% endblock %}
```

For the sake of simplicity, this example does not come with a styled overview of bundles. All you want to do for now, is too see a bundle's `name`, the bundle's assigned
products and the savings from buying it. 

Start with a new container to contain the bundles:
```twig
<div class="tab-pane fade"
     id="bundle-tab-pane"
     role="tabpanel"
     aria-labelledby="bundle-tab">
    <div class="container bundle-container">
        // Bundles here
    </div>
</div>
```

In there you'll have to iterate over all available bundles using the twig for loop.

```twig
{% for bundle in page.product.extension('bundles').elements %}
{% endfor %}
```

For each bundle, you want to show the name, the products and the buy button. 
Due to the `for` loop, you've got access to a single bundle in the `bundle` variable, so just use this one to show the bundle's name:
```twig
{% for bundle in page.product.extension('bundles').elements %}
    <h2>{{ bundle.name }}</h2>
{% endfor %}
```

After the bundle's name, you want to show each assigned product in a separate product box. For this, you'll have to iterate over the `products` association
of a bundle again.
*Note: If you didn't add the `products` association earlier, you wouldn't have access to the products here now.*

For each product, you'll just include the default product box template and apply the current product to it.
```twig
{% for bundle in page.product.extension('bundles').elements %}
    <h2>{{ bundle.name }}</h2>
    <div class="row">
        {% for product in bundle.products.elements %}
            <div class="col-4">
                {% sw_include '@Storefront/storefront/component/product/card/box-standard.html.twig' with {'product': product} %}
            </div>
        {% endfor %}
    </div>
{% endfor %}
``` 

Only the button to put that bundle into the cart now is missing. Since the button has to trigger the checkout process with some data, you'll have to put
it into a form element.
This form has to add an line item to the cart by using the `frontend.checkout.line-item.add` API route for it. A line item is just a raw item in the cart, whatever that means.
You need to recognize the `Bundle` line items later in the process, so also a `type` has to be submitted.
Since you want your button to also open the off canvas cart, you have to add the `OffCanvasCart-Plugin` by adding the data attribute `data-add-to-cart` to your form element.
To protect this form with CSRF-Protection you have to add `data-form-csrf-handler` data attribute to add the `Csrf-Plugin` and use the `sw_csrf` twig-function to generate a csrf-token for the given route name.

Here's the example code, it will be explained afterwards
```twig
<div class="row justify-content-md-center">
    <form action="{{ path('frontend.checkout.line-item.add') }}"
        method="post"
        class="buy-widget js-add-to-cart"
        data-form-csrf-handler="true"
        data-add-to-cart="true">
        <div class="form-row buy-widget-container">
            <button class="btn btn-primary btn-block buy-widget-submit" style="margin-top: 10px;">
                Buy bundle and save {{ bundle.discount }} {{ bundle.discountType == 'absolute' ? context.currency.symbol : '%' }}
            </button>

            <input type="hidden" name="lineItems[{{ bundle.id }}][id]" value="{{ bundle.id }}">
            <input type="hidden" name="lineItems[{{ bundle.id }}][type]" value="swagbundle">
            <input type="hidden" name="lineItems[{{ bundle.id }}][quantity]" value="1">
            <input type="hidden" name="lineItems[{{ bundle.id }}][referencedId]" value="{{ bundle.id }}">
            <input type="hidden" name="redirectTo" value="frontend.cart.offcanvas"/>

            {{ sw_csrf('frontend.checkout.line-item.add') }}
        </div>
    </form>
</div>
```

The first `div` just applies some styles to center the button in the middle by using the `justify-content: center` CSS style.
Afterwards, you can already see the form element. The path to the API route `frontend.checkout.line-item.add` is built by using the [Twig path extension](https://symfony.com/doc/current/reference/twig_reference.html#path).
The data will be sent using the POST method.
Also note the previously mentioned `data-add-to-cart="true"`, which triggers the `OffConvasCart-Plugin` to open the offcanvas cart when putting the bundle to the cart.

Inside the form, you can see the button, which has nothing too special about it. Just some styles being applied with the classes being used.
The button's text will display the savings and has to support percentage as well as absolute discounts, which explains the check for the `discountType`.
It will then either display the currently active currency to show the actual price, or the percentage symbol.
Very important is the hidden `input`'s here, which are necessary to identify your bundle line items later, when adding your custom checkout code to properly
handle your bundles.
You're applying values, such as the `quantity`, the `type` to identify your bundle line items and an ID, which could also be used for identifying.
Since the 'addToCart' route responds with a JSON object containing the status of this action, you don't want to display this response, but instead
show the cart's details inside of the ajax cart. This is done using the `redirectTo`, which will call the cart's detail page's route in order to get an actual
template for the off canvas cart.

### Final bundle template

Your `tabs.html.twig` file should now look like this:

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/tabs.html.twig' %}

{% block page_product_detail_tabs_navigation_description %}
    {{ parent() }}
    {% if page.product.extension('bundles').elements|length > 0 %}
        <li class="nav-item">
            <a class="nav-link" id="bundle-tab" data-toggle="tab" data-offcanvas-tab="true" href="#bundle-tab-pane" role="tab" aria-controls="bundle-tab-pane" aria-selected="false">
                <span>Bundles</span>
            </a>
        </li>
    {% endif %}
{% endblock %}

{% block page_product_detail_tabs_content_description %}
    {{ parent() }}
    {% if page.product.extension('bundles').elements|length > 0 %}
        <div class="tab-pane fade"
             id="bundle-tab-pane"
             role="tabpanel"
             aria-labelledby="bundle-tab">

            <div class="container bundle-container">
                {% for bundle in page.product.extension('bundles').elements %}
                    <h2>{{ bundle.name }}</h2>

                    <div class="row">
                        {% for product in bundle.products.elements %}
                            <div class="col-4">
                                {% sw_include '@Storefront/storefront/component/product/widget/box-standard.html.twig' with {'product': product} %}
                            </div>
                        {% endfor %}
                    </div>

                    <div class="row justify-content-md-center">
                        <form action="{{ path('frontend.checkout.line-item.add') }}"
                            method="post"
                            class="buy-widget js-add-to-cart"
                            data-add-to-cart="true">
                            <div class="form-row buy-widget-container">
                                <button class="btn btn-primary btn-block buy-widget-submit" style="margin-top: 10px;">
                                    Buy bundle and save {{ bundle.discount }} {{ bundle.discountType == 'absolute' ? context.currency.symbol : '%' }}
                                </button>

                                <input type="hidden" name="lineItems[{{ bundle.id }}][id]" value="{{ bundle.id }}">
                                <input type="hidden" name="lineItems[{{ bundle.id }}][type]" value="swagbundle">
                                <input type="hidden" name="lineItems[{{ bundle.id }}][quantity]" value="1">
                                <input type="hidden" name="lineItems[{{ bundle.id }}][referencedId]" value="{{ bundle.id }}">
                                <input type="hidden" name="redirectTo" value="frontend.cart.offcanvas"/>
                            </div>
                        </form>
                    </div>
                {% endfor %}
            </div>
        </div>
    {% endif %}
{% endblock %}


```

## Adding translations to the Storefront

You might have noticed multiple occurrences of static texts, which still need some translations. Applying translations to the Storefront works a little bit different than
in the Administration.
It's not that much of a deal though, so don't worry.

### Adding SnippetFiles

Adding snippets via plugins works by registering services via the DI container tag `shopware.snippet.file`.
Those services implement the `Shopware\Core\System\Snippet\Files\SnippetFileInterface` interface, which needs five methods to be implemented:
- `getName`: Return the name of the snippet file as a string here. Using this name, you can access the translations later. By default, you can return `storefront.en-GB` here.
- `getPath`: Each SnippetFile class has to point to a `.json` file, which actually contains the translations. Return the path to this file here.
- `getIso`: Return the ISO string of the supported locale here. This is important, because the `Translator` collects every snippet file with this locale and merges them to generate the snippet catalogue used by the storefront. 
- `getAuthor`: Guess what, return your vendor name here. This can be used to distinguish your snippets from all the other available ones.
- `isBase`: Return `true` here, if your plugin implements a whole new language, such as providing french snippets for the whole Shopware 6.
In this case, you're just adding your own snippets to an existent language, so use `false` here.

Now start of by adding this new directory: `<plugin root>/src/Resources/snippet`
In there, create a new directory for each locale you want to support, `en_GB` and `de_DE` as supported by this example plugin, the focus will be on the english one though. 
Inside the `en_GB` directory, create the new file `SnippetFile_en_GB.php`, which will also be the class name.

Having implemented all methods mentioned above, your `SnippetFile_en_GB.php` should look like this:
```php
<?php declare(strict_types=1);

namespace Swag\BundleExample\Resources\snippet\en_GB;

use Shopware\Core\System\Snippet\Files\SnippetFileInterface;

class SnippetFile_en_GB implements SnippetFileInterface
{
    public function getName(): string
    {
        return 'storefront.en-GB';
    }

    public function getPath(): string
    {
        return __DIR__ . '/storefront.en-GB.json';
    }

    public function getIso(): string
    {
        return 'en-GB';
    }

    public function getAuthor(): string
    {
        return 'Enter developer name here';
    }

    public function isBase(): bool
    {
        return false;
    }
}
```

As you might notice, it points to a `storefront.en-GB.json` file in the same directory. This is also the file you need to create now.
In there, you can store all the translations you want to use, just like you've done in the administration snippets.

### Register to services.xml

Now register your `SnippetFile` in the DI container using the `shopware.snippet.file` tag.

```xml
<service id="Swag\BundleExample\Resources\snippet\en_GB\SnippetFile_en_GB">
    <tag name="shopware.snippet.file" priority="100"/>
</service>
```

That's it already.

### Filling the translations

Now you can fill the `storefront.en-GB.json` file with all the translations you need.
There's several occurrences in the code so far, that would need a proper translation:
- `index.html.twig`: The badge text needs a translation
- `tabs.html.twig`: The tab text inside of the `a` element is statically set to `Bundles` and needs a translation.
- `tabs.html.twig`: The 'Add to cart' button for a bundle also needs a translation. Since we're dealing with variables here, use it like this:

Simply fill the `.json` files with your desired translations now and then add them to the template like this:
```twig
{{ "swag-bundle.detail.bundleBadge"|trans }}
```

This example would be saved in the `json` file like this:
```json
{
    "swag-bundle": {
        "detail": {
            "bundleBadge": "Bundle"
        }
    }
}
```

*Note: Make sure to clear the cache after having added snippets.*

If you're dealing with variables, use it like this instead:
```json
{
    "swag-bundle": {
        "detail": {
            "bundleBadge": "Bundle",
            "buyButtonText": "Buy bundle and save %bundleDiscount%"
        }
    }
}
```

In your template:
```twig
<button class="btn btn-primary btn-block buy-widget-submit" style="margin-top: 10px;">
    {{ 'swag-bundle.detail.buyButtonText'|trans({ '%bundleDiscount%': bundle.discountType == 'absolute' ? bundle.discount|currency : (bundle.discount ~ '%') }) }}
</button>
```

**And with that, your extension for the product detail pages is ready to go and working!**

Now, that you can extend from other templates, start working on the checkout logic.
This is done in [next step](./090-checkout.md).
