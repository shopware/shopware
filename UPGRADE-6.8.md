# 6.8.0.0

# Changed Functionality

# API

# Core

# Administration

# Storefront

# App System

## Use `sw_macro_function` instead of usual `macro` in app scripts if you return values

Return values over the `return` keyword from usual twig `macro` functions are not supported anymore.
Use the `sw_macro_function` instead, which is available since v6.6.10.0.

```diff
// Resources/scripts/include/media-repository.twig
- {% macro getById(mediaId) %}
+ {% sw_macro_function getById(mediaId) %}
    {% set criteria = {
        'ids': [ mediaId ]
    } %}
    
     {% return services.repository.search('media', criteria).first %}
- {% endmacro %}
+ {% end_sw_macro_function %}

// Resources/scripts/cart/first-cart-script.twig
{% import "include/media-repository.twig" as mediaRepository %}

{% set mediaEntity = mediaRepository.getById(myMediaId) %}
```
