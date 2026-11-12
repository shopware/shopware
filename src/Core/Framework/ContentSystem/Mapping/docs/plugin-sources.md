# Plugin sources and explicit overrides

For a new plugin-owned source, use the bundle name as the source `type` and a bundle-local source name as `id`:

```php
$source = new MappingSourceReference('AcmePlugin', 'recommendations', path: 'title');
```

This reference has the display name `AcmePlugin:recommendations.title` and the inline token `{{map:AcmePlugin:recommendations.title}}`. Another bundle can offer its own `recommendations.title` without a collision. Use the same reference in the candidate returned by `provide()` and handled by `resolveSource()`, and declare the same display name in `registeredCandidateKeys()` when it is stable. The bundle name must fit the inline token identifier grammar to offer inline tokens. Keep names stable because layouts persist them. Names are supplied explicitly; existing references and root paths such as `product.name` are not automatically rewritten or prefixed. Labels remain independent of source identifiers.

To change an existing core mapping intentionally, decorate the public `AbstractContentSystemMappingCandidateRegistry` service. Do not register another provider with the same name. Concrete core provider classes remain internal. Forward `forRootSource()` to preserve the catalogue when only changing a value; if changing metadata or its effective type, keep admission and runtime resolution consistent for both whole-field and inline mappings.

Override the protected `resolveAdmittedSource()` hook rather than the final public `resolveSource()` method. The public method checks the requested source, including configuration, against the effective catalogue and records attempted-source and candidate metadata dependencies before calling the hook. An unavailable or mismatched source returns `noValue()` without invoking plugin resolution. Forward unrelated sources to the inner registry's public `resolveSource()` and add any new data dependencies to the supplied rendering cache context.

For example, a decorator's `resolveAdmittedSource()` hook for `product.name` can first delegate to the inner registry's `resolveSource()`, then transform a resolved string only when the root source is `product` and the reference exactly matches `MappingSourceReference::root('product', 'name')`. Missing data, explicit null, rejected references, and provider errors retain their [normal handling](../README.md#stored-mappings-and-rendering). Its `getDecorated()` returns the inner registry. The inherited `reset()` forwards to that registry; a decorator with its own request-local cache must clear it and forward the reset too.

`AbstractContentSystemMappingCandidateRegistry` is a public service alias whose default target is the internal `ContentSystemMappingCandidateRegistry`. Catalogue requests, mapping admission, and runtime resolution all use the abstract service, so a decorator applies consistently to whole-field and inline mappings. Register the decorator using Symfony service decoration, with its `.inner` service injected as `AbstractContentSystemMappingCandidateRegistry`:

```xml
<service id="AcmePlugin\Mapping\ProductNameRegistry"
         decorates="Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry">
    <argument type="service" id="AcmePlugin\Mapping\ProductNameRegistry.inner"/>
</service>
```

A decorator that changes only catalogue metadata may inherit the default resolution hook, which forwards to the inner registry. Catalogue admission cannot verify the origin of a plugin-returned value: providers and decorators remain responsible for exposing Storefront-safe data. Property opt-in, cardinality, and runtime value types continue to be checked by mapping admission and rendering.

Multiple intentional decorators follow Symfony's explicit decoration ordering. Duplicate provider registrations remain conflicts rather than an override mechanism.
