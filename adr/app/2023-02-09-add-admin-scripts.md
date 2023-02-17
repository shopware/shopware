# 2023-02-09 - Add admin scripts

We want to add admin scripts, that generates pages for the admin.
With this you can, for example, offer an HTML page with an editor for entities.
Or you can use them for pages displayed in iframes from the Admin Extension SDK.

## Admin Script
An admin script runs on POST or GET requests to URLs like `/api/app/hook-name`.
The script can be placed in the scripts directory of an app.
The app does not have to have a secret or use external services.
It has an authentication through the hidden cookie "admin_auth".
The cookie has as value the "access_token" and it has the flags `HttpOnly` and `SameSite` for security reasons.
You can call the scripts via the URL, when you are logged in to the admin.
It has an additional security service to disable or change the browser's HTML security options.
It uses existing parameters and functions for the translations.
It supports snippet translation in the twig templates.

## Example

You could add a action button to the product details in the administration:

### manifest.xml
```twig
<action-button action="product-detail-tab"
  entity="product" view="detail"
  url="/api/script/open-in-new-tab">
    <label>Open editor in new window</label>
</action-button>
```

If you click on this action button, this app script could be executed:

### Resources/scripts/api-open-in-new-tab/index.twig
```twig
{% set redirectUrl = path('administration.script', {
    'hook': 'editor',
    'entityId': hook.request.data.ids|first,
    'entity': hook.request.data.entity,
    'sw-context-language': hook.context.languageId
}) %}
{% set response = services.response.json({
    "actionType": "openNewTab",
    "payload": {
        "redirectUrl": redirectUrl
    }
}) %}
{% do hook.setResponse(response) %}
```

The script generates a redirect URL from the context and request parameters.
The URL is written into a JSON response for administration.
You could then be redirected to the admin script "editor".
The source code of this app script could be:

### Resources/scripts/admin-editor/index.twig
```twig
{% if hook.request %}
    {% set request = hook.request %}
    {% set request = request|merge({'id': hook.query.entityId}) %}
    {% do services.writer.upsert(hook.query.entity, [request]) %}
    {% do services.router.redirect('editor', hook.query) %}
{% else %}
    {% set entity = services.repository.search(
        hook.query.entity, { 'ids': [hook.query.entityId]}
    ).first %}
    {% set content = include('include/editor.twig') %}
    {% set response = services.response.raw(content) %}
    {% do hook.setResponse(response) %}
{% endif %}
```

If they are request parameters, the product will be updated with those parameters
and then you will be redirected to the editor page again.
If it isn't a save request, the product is loaded from the repository
and a response is generated in a script include.
The include with an editor can look like this:

### Resources/scripts/include/editor.twig
```twig
<form method="post">
    <h5 class="m-3">{{ 'editor.description'|trans() }}</h5>
    <textarea name="description"
                id="description">{{ entity.description }}</textarea>
    <button>
        {{ 'editor.save'|trans() }}
    </button>
</form>
```
