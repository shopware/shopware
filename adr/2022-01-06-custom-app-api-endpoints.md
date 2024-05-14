---
title: Allow apps to define custom api endpoints
date: 2022-01-06
area: core
tags: [admin-api, store-api, app-system]
---

## Context
Apps should be allowed to provide their own API and Store-API and Storefront endpoints where they can execute different logics that deviate from the automatic entity API.

## Decision

### API 
We implement two new endpoints: 
- `/api/script/{hook}`.
- `/store-api/script/{hook}`.

The `{hook}` parameter is used as the script hook name and prefixed with the url prefix (`api-`, `store-api-`).

This hook is then executed, and apps have the possibility to load or even write data in the scripts.

The following data is given to the script:
* [array] request.request.all
* [context/sales channel context] context

By default, multiple scripts can be executed on a single hook; however, we will add a `hook.stopPropagation()` method to all API-Hooks, if that was called no further scripts will be executed.
Furthermore, we will document that the hook-name the app developer chooses should contain the vendor-prefix to prevent unwanted overrides from other apps.

### Storefront 
We implement a new endpoint:
- `/storefront/script/{hook}`

The `{hook}` parameter is used as the script hook name and prefixed with the url prefix (`storefront-`).

In this hook, the app can load or write data and either return a script response or render a twig template as a response.

The following data is given to the script:
* [array] request.request.all
* [array] request.query.all
* [sales channel context] context
* [GenericPage] page

#### Response handling

We will add a new `response` service that provides factory methods to create response objects. The returned Response object is a generic wrapper around one of the following responses: `JsonResponse`, `RedirectResponse`, `StorefrontResponse`.

To output the created response, it has to be assigned to the hook:
```twig
{% do hook.setResponse(response) %}
```
If no response is set, an empty 204 response will be sent as default.

##### Returning a custom JsonResponse

The json() method allows to specify the data and the http-status code to be returned:
```twig
{% set response = services.response.json({'data': data}, statusCode) %}
```

##### Redirecting

The redirect() method allows to specify a route and route params, to which should be redirected, and an optional statusCode (302 is default):
```twig
{% set response = services.response.redirect('routeName', params, statusCode) %}
```

##### Rendering a template

The render() factory allows to pass the template name and the parameters (the page object and additional params) and will perform the `StorefrontController->renderStorefront()`.
```twig
{% set response = services.response.render('@myApp/storefront/pages/my-custom-page.html.twig', { 'page': hook.page }) %}
```
If it is called outside of a SalesChannelContext (e.g., from an `/api` endpoint) or called on installations that don't have the storefront-bundle installed it will throw an exception.

#### Login Protection

We will add a helper method to the SalesChannelContext to ensure that a customer is logged in before continuing to execute the script. The helper method will check if there is a customer in the current `SalesChannelContext` 
and will throw an `CustomerNotLoggedInException` if there is no customer logged in.
```twig
{% do hook.context.ensureLogin() %}
```

#### Caching

Our script response wrapper allows modifying the caching strategies for the responses.
```twig
{% do response.cache.invalidationState('logged-in', 'cart-filled') %}
{% do response.cache.maxAge(7200) %}
{% do response.cache.disable() %}
{% do response.cache.tag('my-manufacturer-tag-' ~ manufacturerId, 'another-tag') %}
```
By default all /storefront and /store-api routes are cached, so caching it is opt-out for those routes.
For the /api routes caching is not supported, if you provide cache configuration on the response of those routes, it will be ignored.

For individual cache invalidation, we add a new `cache-invalidation`-hook point. That hook-point is a hook on the general EntityWrittenContainerEvent.
The app can analyze the write payload of the event and use a cache-invalidation service to invalid the cache for a given tag.

We will wrap the EntityContainerEvent, so scripts are forced to specify the entity for which they want to inspect the write payload.
Instead of providing the raw payload, we will provide a fluid, functional interface which allows to filter for entityIds that match some criteria.

```twig
{% set ids = hook.event.getIds('manufacturer') %}
{% set ids = ids.only('upated') %} // only update events
{% set ids = ids.with(['name', 'url']) %}  // with name OR url cahnge

{% set ids = hook.event.get('manufacturer').only('upated').with(['name', 'url']) %} // same as above but chained

{% if ids.empty %}
    {% return %}
{% endif %}

{% set tags = [] %}
{% for id in ids %}
    {% set tags = tags|merge(['my-manufacturer-tag-' ~ id]) %}
{% endfor %}

{% do services.cache.invalidate(tags) %}
```

#### No XML-config

App-Scripts in general and custom api endpoints in particular work without further configuration inside the manifest.xml file. 
We prefer solutions inside the scripts over a solution that would require additional configuration in the xml file.
The reason is that everything regarding app scripts is in one place inside the app itself, namely the `Resources/scripts` folder.
Additionally, the manifest.xml can get outdated which may lead to confusing errors, and in general, the structure of the xml file is more limited than the possibilities we have in the app scripts itself.

#### SEO-Urls

We won't add seo urls in this iteration, the reason is that that feature is pretty complex, and we don't know yet if the feature would be used at all or not.
Additionally, a feature like that would add a heavy maintenance burden because of the tight coupling to the general seo_url solution, and we just don't know yet if the feature brings more value

We also dropped the idea of custom-routes aka the (static) seo urls light alternative, because it is an overly specific solution

We prefer more general solutions, as we can't anticipate all use cases the app developers may have, and we can't possibly build a custom solution for every use case they may have.
Therefore, we will create a separate ticket/ADR to add lifecycle scripts to the app scripts. A script like that could be used to add entries into the seo_url table with aliases for the script routes but is not limited to that use case.
It will greatly simplify the use case that on installation of the app something should be changed/added in the DB of the shop (the current way to go would be to add a webhook on the app_install event and build an external service that in turn uses the api to change stuff, we would eliminate the need of the external server)
