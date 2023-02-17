---
title: Add admin scripts - Create admin pages with scripts.
issue: NEXT-
author: Heiner Lohaus
author_email: heiner@lohaus.eu
author_github: hlohaus
---
# Admin scripts
* Added a admin script abelity for own pages in the administration.
Places the scripts under:
```
Resources/scripts/admin-hook-name/index.twig
```
* And call them with requests on urls like this:
```
/api/app/hook-name
```
## Security service
*New security service with nonce function, which returns the nonce value from the content security policy:
```twig
<script nonce="{{ services.security.nonce() }}"></script>
```
* Creates a response with a disabled content security policy:
```twig
{% do services.security.setContentSecurityPolicy('') %}
{% set response = services.security.response(content) %}
```
* Sets frame options in the result of an admin script. So that the pages can be displayed in iframes.
```twig
{% do services.security.setFrameOptions('sameorigin') %}
{% set response = services.security.response(content) %}
```
## Router service
* Creates admin script urls with the router service.
If you don't specify a hook, the current one is used.
```twig
{% set url = services.router.generate('admin-hook', query) %}
```
* Or just redirect the request to another admin script:
```twig
{% do services.router.redirect('admin-hook', query) %}
```
## Translations
* Use snippets for translations in scripts:
```twig
{{ 'general.admin.description'|trans() }}
```
* Automatically reading entity translation with language ID from request query. The request url:
```
/api/app/hook?sw-context-language=2fbb5fe2e29a4d70aa5854ce7ce3e20b
```
* Example of reading the translated name with the repository:
```twig
{% set productName = services.repository.search(
    'product', { 'ids': [hook.query.productId]}
).first.name %}
```
## Raw response
* Added a raw function to the response service for e.g. HTML responses:
```twig
{% set content = include('include/content.twig') %}
{% set response = services.response.raw(content, 200, 'text/html') %}
{% do hook.setResponse(response) %}
```
## Routing extension
* Added the path and url functions from the routing extension in scripts. Use it to generate URLs in e.g. API scripts.

```twig
{% set redirectUrl = path('administration.script', {
    'hook': 'editor',
    'entityId': hook.request.data.ids|first,
    'entity': hook.request.data.entity,
    'sw-context-language': hook.context.languageId
}) %}
```
## Bug fix
* Bug fix: Internal resolved actions buttons now includes the complete payload. `Shopware\Core\Framework\App\ActionButton\Executor`


## Refactor
* Added: `Shopware\Core\Framework\Script\Api\ScriptResponseEncoder::encodeByHook` Less duplicate code
* Added: `Shopware\Core\Framework\Script\Execution\HookNameTrait` Less duplicate code
* Added: `Shopware\Core\Framework\Script\Execution\ScriptContextValidator` Less duplicate code
