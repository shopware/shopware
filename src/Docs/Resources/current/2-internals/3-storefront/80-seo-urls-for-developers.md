[titleEn]: <>(Seo Urls For Developers)

This document aims to provide a broad overview of how seo urls in shopware 6 work from a developers perspective.
 
## Seo Urls

Seo Urls are implemented as entities in Shopware 6. Each seo url links to specific a entity and can use it's content during url generation (see [below](#seo-url-templates)). 
Seo Urls are assigned on a per language per sales channel basis. This allows each sales channel to have it's own sets of seo urls for each language in the shop,
to account for translated product names. If you do not need different urls for each shop, fallback urls are generated with a `SalesChannelId` of `null`.
Additionally, exactly one of the urls of an entity can be the `canonical` url, again, on a per sales channel per language basis. 
Furthermore, seo urls are never deleted (by default). If the url of an entity changes, but the content itself is still available, this enables for automatic redirection of "old" urls. 
Noteworthy attributes a seo url entity include:

* `languageId`: The id of the language this seo url applies to
* `salesChannelId`: The id of the sales channel this url applies to. A value of `null` signals that this url is a fallback, if no specific url for the current sales channel is found.
* `foreignKey`: The primary key of the entity this url links to. The type of the entity is defined through the `routeName'.
* `routeName`: The technical name of the route. See [templates](#seo-url-templates) for a description of the parameter. 
* `pathInfo`: The technical url of this entity for the route to which the seo url will link to. Should correspond to a url caught by the controller specified by `routeName`. See [templates](#seo-url-templates).
* `seoPathInfo`: The seo url string
* `isCanonical`: Whether this url is the canonical url for this language, sales channel, foreign key and route name combination. `true` if this url is canonical, `null` if not.
* `isModified`: Whether this url is a manual override. Manual overrides are not generated through templates, but have been specified by a user, such as campaign urls etc. Modified url always take precedence over generated urls.
* `isDeleted`: Whether the linked entity of this seo url was deleted. 

We use the `null` value as substitute for `false` for the `isCanonical` field, because it enables us to use a single unique constraint on the database level. 
This assert that one, and only one, url for a entity-sales channel-language-route-entity combination is marked as canonical. 
As `null` values are not considered when unique constraints are checked, multiple non canonical urls can exist. `false` must never be used here.

### Resolution rules

As you might have noticed, there are some rules which allow certain Seo Urls to take precedence over others when a request is resolved.
To give you a better overview of how seo urls are resolved, here is a list of all rules, ordered by their priority: 
1. Urls which are assigned to the sales channel of this request and are marked as manual overrides (`isModified`)
2. Urls which are assigned to the sales channel of this request
3. Urls which are assigned to the sales channel fallback (NULL) and are marked as manual overrides (`isModified`)
4. Urls which are assigned to the sales channel fallback (NULL)
5. If no canonical url matches the address for the current language in a request, or the matched url is a `deleted` url, a 404 response is returned.

## Seo Url Templates
Seo Url Templates define how a seo url for given entity is generated. These templates use the [Twig template syntax](https://twig.symfony.com/) and can access all the properties of the entity a generated url should link to.
A seo url template has the following, noteworthy attributes:

* `salesChannelId`: The id of an sales channel, to which this template is assigned. Can be `null` to signal that this template is a fallback value, if no specific sales channel is defined.
* `entityName`: The technical name of the entity definition, to which seo urls will link 
* `routeName`: The technical name of the route to which the generated urls will link. Must be the same as the name specified in the `@Route` [annotation](https://symfony.com/doc/current/routing.html#creating-routes-as-annotations).
* `template`: The template string, which will be used in url generation
* `isValid`: Whether this template is valid. Templates can be invalid if they contain syntax errors or use undefined variables. Only valid templates can be used for url generation.

There may only be one unique template per `routeName` and sales channel.

### Example templates

As the seo url template system uses twig, most of the twig features are available.

As a simple example, the product template:
```twig
{{ product.id }}
```
generates a seo url which contains the primary key of the product. 

[Filters](https://twig.symfony.com/doc/2.x/filters/index.html) can be applied and chained as usual.
The product template 
```twig
{{ product.name|upper }}-{{product.createdAt|date("m-d-Y")}}
```
outputs the name of product in CAPITAL letters, followed by it's creation date.

By default, all generated urls are run through the [slugify Filter](https://github.com/cocur/slugify), to escape
unsafe characters and create valid urls. This filter is also available in templates, so you can postprocess the default escaping behaviour.
Note that slugify is always run after url generation in any case, even if you have already used in your template, so you'll always generate valid urls.

### Generation process

Seo Urls are generated through the `SeoUrlIndexer`. This Indexer is triggered if one the the following two events is raised:

* A complete reindex is triggered (e.g. through the command `dal:refresh:index`). In this case all seo urls will be regenerated.
* An `EntityWrittenContainerEvent` is fired which affects an entity with a seo url. In this case, only the affected entities are updated.

Newly generated urls are marked as canonical by default, even though this behaviour can be influenced by developers.
In any case URLs are not rewritten if they would not change. This means that if the same canonical url is generated for the same entity, route, sales channel and language, it is not written again.
If the urls is the same as a previously generated url, but the old entry is marked as non-canonical, it is set to canonical again.

### Adding Seo Urls for your own Routes

To add your own Seo Urls for custom routes, you'll need

1. A custom controller which catches the entity you want to link to, e.g. `myentity/{id}`
2. Your own `SeoUrlRouteInterface`, which maps a template to a route name and entity.
3. Through a migration you'll have to manually add the fallback template once

A detailed "how to add custom routes for urls" will be available soon.
