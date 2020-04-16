[titleEn]: <>(SEO URLs for developers)
[hash]: <>(article:storefront_seo_urls_devs)

This document aims to provide a broad overview of how SEO URLs in Shopware 6 work from a developers perspective.
 
## SEO URLs

SEO URLs are implemented as entities in Shopware 6.
Each SEO URL links to a specific entity and can use its content during URL generation (see [below](#seo-url-templates)). 
SEO URLs are assigned on a per language per sales channel basis.
This allows each sales channel to have its own sets of SEO URLs for each language in the shop,
to consider translated product names.
If you do not need different URLs for each shop, fallback URLs are generated with a `SalesChannelId` set to `null`.
Additionally, exactly one of the URLs of an entity can be the `canonical` URL, again, on a per sales channel per language basis. 
Furthermore, SEO URLs are never deleted (by default).
If the URL of an entity changes, but the content itself is still available, an automatic redirect of "old" URLs is enabled. 
Noteworthy attributes of a SEO URL entity include:

* `languageId`: The ID of the language this SEO URL applies to
* `salesChannelId`: The ID of the sales channel this URL applies to. A value of `null` signals that this URL is a fallback, which takes effect if no specific URL for the current sales channel is found.
* `foreignKey`: The primary key of the entity this URL links to. The type of the entity is defined through the `routeName'.
* `routeName`: The technical name of the route. See [templates](#seo-url-templates) for a description of the parameter. 
* `pathInfo`: The technical URL of this entity for the route to which the SEO URL will link to. Should correspond to a URL caught by the controller specified by `routeName`. See [templates](#seo-url-templates).
* `seoPathInfo`: The SEO URL string
* `isCanonical`: Whether this URL is the canonical URL for this language, sales channel, foreign key and route name combination. `true` if this URL is canonical, `null` if not.
* `isModified`: Whether this URL is a manual override. Manual overrides are not generated through templates, but have been specified by a user, such as campaign URLs etc. Modified URL always take precedence over generated URLs.
* `isDeleted`: Whether the linked entity of this SEO URL was deleted. 

We use the `null` value as substitute for `false` for the `isCanonical` field, because it enables us to use a single unique constraint on database level. 
This assert that one, and only one, URL for a entity-sales channel-language-route-entity combination is marked as canonical. 
As `null` values are not considered when unique constraints are checked, multiple non canonical URLs can exist.
`false` must never be used here.

### Resolution rules

As you might have noticed, there are some rules which allow certain SEO URLs to take precedence over others when a request is resolved.
To give you a better overview of how SEO URLs are resolved, here is a list of all rules, ordered by their priority: 
1. URLs which are assigned to the sales channel of this request and are marked as manual overrides (`isModified`)
2. URLs which are assigned to the sales channel of this request
3. URLs which are assigned to the sales channel fallback (`null`) and are marked as manual overrides (`isModified`)
4. URLs which are assigned to the sales channel fallback (`null`)
5. If no canonical URL matches the address for the current language in a request, or the matched URL is a `deleted` URL, a 404 response is returned.

## SEO URL templates
SEO URL templates define how a SEO URL for given entity is generated.
These templates use the [Twig template syntax](https://twig.symfony.com/) and can access all the properties of the entity a generated URL should link to.
A SEO URL template has the following, noteworthy attributes:

* `salesChannelId`: The ID of a sales channel, to which this template is assigned. Can be `null` to signal that this template is a fallback value, if no specific sales channel is defined.
* `entityName`: The technical name of the entity definition, to which SEO URLs will link 
* `routeName`: The technical name of the route to which the generated URLs will link. Must be the same as the name specified in the `@Route` [annotation](https://symfony.com/doc/current/routing.html#creating-routes-as-annotations).
* `template`: The template string, which will be used in URL generation
* `isValid`: Whether this template is valid. Templates can be invalid if they contain syntax errors or use undefined variables. Only valid templates can be used for URL generation.

There may only be one unique template per `routeName` and sales channel.

### Example templates

As the SEO URL template system uses Twig, most of the Twig features are available.

As a simple example, the product template:
```twig
{{ product.id }}
```
Generates a SEO URL which contains the primary key of the product. 

[Filters](https://twig.symfony.com/doc/2.x/filters/index.html) can be applied and chained as usual.
The product template 
```twig
{{ product.name|upper }}-{{product.createdAt|date("m-d-Y")}}
```
Outputs the name of product in CAPITAL letters, followed by its creation date.

By default, all generated URLs are run through the [slugify Filter](https://github.com/cocur/slugify),
to escape unsafe characters and create valid URLs.
This filter is also available in templates, so you can postprocess the default escaping behaviour.
Note that slugify is always run after URL generation in any case, even if you have already used in your template,
so you'll always generate valid URLs.

### Generation process

SEO URLs are generated through the `SeoUrlIndexer`.
This indexer is triggered if one the the following two events are raised:

* A complete reindex is triggered (e.g. through the command `dal:refresh:index`). In this case all SEO URLs will be regenerated.
* An `EntityWrittenContainerEvent` is fired which affects an entity with a SEO URL. In this case, only the affected entities are updated.

Newly generated URLs are marked as canonical by default, even though this behaviour can be influenced by developers.
In any case URLs are not rewritten if they would not change.
This means that if the same canonical URL is generated for the same entity, route, sales channel and language, it is not written again.
If the URLs is the same as a previously generated URL, but the old entry is marked as non-canonical, it is set to canonical again.

### Adding SEO URLs for your own Routes

To add your own SEO URLs for custom routes, you'll need

1. A custom controller which catches the entity you want to link to, e.g. `myentity/{id}`
2. Your own `SeoUrlRouteInterface`, which maps a template to a route name and entity.
3. Through a migration you'll have to manually add the fallback template once

A detailed "how to add custom routes for URLs" will be available soon.

## FAQ
This document was created for Shopware 6.0.0 EA2

### Is there any way to change the seo urls in EA2?

EA2 introduces a new module to the administration which allows you to change the template after which the urls are generated.
The module is located at `Settings=>SEO`. 

The templates use the [Twig Template syntax](https://twig.symfony.com/).

Templates can be changed per sales channel using the dropdown at the top of the module. Using the "All Sales Channels" entry you can define
a default template which will be used if there is no specific template for a sales channel.

If you want to change the urls for a specific sales channel, you have to remove the inheritance by clicking on the purple chain.

### I've updated Shopware from a previous version to ea2, but the urls are still the same

During the update process all seo urls are created in a scheduled task. Scheduled tasks are processed asynchronously by a worker. 
The default worker is called `AdminWorker` and is executed only when a user enters the administration.
Please enter the administration or setup a [cli-worker](./../1-core/00-module/scheduled-tasks.md#the-task-scheduler).

If the problem still persists, you can force a recreation of all seo urls using a console command.
To do so, enter a shell inside your shopware directory and type
```bash
bin/console dal:refresh:index
```
After the command has finished the new seo urls will be used in the storefront immediately.

### I have changed my seo url template, but the seo urls remain unchanged

In EA2 seo urls are not recreated when the template changes. This behaviour is **not** final
and will most likely change in a upcoming version.

In the meantime you will have to manually recreate the seo urls using `dal:refresh:index`, as described above.

### I've changed the sales channel using the dropdown at the top of the seo module, but I cannot edit the template

You have to remove the inheritance from the `All Sales Channels` setting by clicking the purple chain.

### What happens to old urls if they are replaced by new ones?

Whenever you change the seo url of an entity, old urls are never deleted. Instead, the old urls
are flagged as such internally and will not be used to display content.  

Even though they are not broken. If you use use an old link to navigate to content which still exists it will be returned as usual.

### If old urls are not redirected, doesn't this lead to duplicate content?

No. Instead of using redirects Shopware 6.0.0 EA2 uses the canonical HTTP header or HTML link tag to inform search engines which url is canonical.
For more information, see [this post by Google](https://support.google.com/webmasters/answer/139066?hl=en#rel-canonical-header-method).
The method in use may change in the future.

### I've searched over the complete HTML and I cannot find the canonical tag!

Some browser do not include the `<head>` part in search queries when using the default browser tools (like the developer console).
You'll either have to use a diffrent editor or search manually. 

### I'd rather use a 301 HTTP code to redirect old seo urls

This feature is not availabe in Shopware 6.0.0 EA2, but may be implemented in future versions.

### I want to assign url to a specific product

The ability to override seo urls on a per product/ per category basis has not made it into the administration with EA2. We expect it to arrive with the next release.

Even though the technical basis is already there and can be used by developers.
