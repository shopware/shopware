#Feature Flags

-- To be Filled --

### Twig
With the implemented twig-function "feature()" you can check if a given featureFlag is active or not.

```twig
{% if feature('flagName') %}
Only visible when FeatureFlag is active
{% else %}
Only visible when FeatureFlag is inactive
{% endif %}
```
if  "flagName" is not registered, a Twig_Error_Runtime-Exception is thrown
