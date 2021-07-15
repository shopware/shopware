---
title: Optimize storefront script loading
issue: NEXT-15917
flag: FEATURE_NEXT_15917
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Storefront
* Deprecated script twig blocks inside `Resources/views/storefront/base.html.twig`
    * Deprecated block `base_script_token` - Use block `layout_head_javascript_token` inside `Resources/views/storefront/layout/meta.html.twig` instead
    * Deprecated block `base_script_router` - Use block `layout_head_javascript_router` inside `Resources/views/storefront/layout/meta.html.twig` instead
    * Deprecated block `base_script_breakpoints` - Use block `layout_head_javascript_breakpoints` inside `Resources/views/storefront/layout/meta.html.twig` instead
    * Deprecated block `base_script_csrf` - Use block `layout_head_javascript_csrf` inside `Resources/views/storefront/layout/meta.html.twig` instead
    * Deprecated block `base_script_wishlist_state` - Use block `layout_head_javascript_wishlist_state` inside `Resources/views/storefront/layout/meta.html.twig` instead
    * Deprecated block `base_script_hmr_mode` - Use block `layout_head_javascript_hmr_mode` inside `Resources/views/storefront/layout/meta.html.twig` instead
* Changed `Resources/app/storefront/src/main.js` and execute `PluginManager.initializePlugins()` on the `DOMContentLoaded` event
* Changed `<script>` tags in block `base_script_hmr_mode` inside template `Resources/views/storefront/base.html.twig` and removed `async` attribute
* Changed `<script>` tag in block `component_head_javascript_recaptcha` inside template `Resources/views/storefront/component/recaptcha.html.twig` and removed `async` attribute
___
# Upgrade Information

## Change the script tag location in the default Storefront theme

All `base_body_script` child blocks and their `<script>` tags are moved from `Resources/views/storefront/base.html.twig` to `Resources/views/storefront/layout/meta.html.twig`. The block `base_body_script` itself remains in the `base.html.twig` template to offer the option to inject scripts before the `</body>` tag if desired.

The scripts got a `defer` attribute to allow downloading the script file while the HTML document is still loading. The script execution happens after the document is parsed.

Example for a `<script>` extension in the template:

### Before

```html
{% sw_extends '@Storefront/storefront/base.html.twig' %}

{% block base_script_router %}
    {{ parent() }}

    <script type="text/javascript" src="extra-script.js"></script>
{% endblock %}
```

### After

```html
{% sw_extends '@Storefront/storefront/layout/meta.html.twig' %}

{% block layout_head_javascript_router %}
    {{ parent() }}

    <script type="text/javascript" src="extra-script.js"></script>
{% endblock %}
```
