---
title: Introduce Product Type And Deprecate Product States
date: 2025-11-14
area: inventory
tags: [store-api, inventory, structure]
---

# Introduce Product Type And Deprecate Product States

## Context

Currently, the product.states field has various issues:

* Not clear semantics:
  - It mixes multiple responsibilities (download/physical markers, per-row flags).
  - A product never changes from digital to physical or vice versa, but the field is updated on every save even if no relevant changes were made. Hence, the term "states" is ambiguous and does not clearly convey the purpose of the field, as for other `states` in other entities, for e.g `order.states`, it should represent the lifecycle state of the entity, but in this case, it represents product types.
  - A product cannot be both digital and physical, but the field is a JSON array.
  - We need a single authoritative indicator for whether a product/line-item is digital or physical that can be easily queried by DAL, Elasticsearch, Cart processors, and rule conditions.

* Performance:
  - The field is not indexed as it is a JSON array, so simple filtering (e.g. "only digital products") is slow and complex.
  - `StatesUpdater` was also not optimal for performance, it runs on every product save and updates the entire product record even if no relevant changes were made but in theory, once a product is marked as digital or physical, it should not be changed.

* Extensibility:
  - `product.states` are updated by the `StatesUpdater` based on the presence of downloads; if a product has downloads, it gets the `is-download` state, otherwise `is-physical`. This should be fine for platform use cases, but it is not flexible for third-party extensions that may want to introduce new product types.
  - The current implementation does not provide a straightforward way for third-party developers to add new product types or states  (e.g. bundle, container, etc.).
  - The rule conditions and product stream filters are tightly coupled to the legacy states (hard coded in both client-side and server-side), making it difficult to extend or modify their behavior. For e.g a third-party developer wanting to add a new product type, they would need to modify the existing rule conditions, product stream filters, product listing filters which is not ideal.

## Decision

### Deprecation of `product.states`:

- Deprecate the `product.states` field in the database in favor of a new `product.type` field that clearly indicates whether a product is `digital` or `physical`.
- Deprecate `order_line_item.states` in favor of `order_line_item.payload.product_type` in a similar manner.
- Deprecate `LineItemProductStatesRule` in favor of `LineItemProductTypeRule`.
- Deprecate `StatesUpdater` service and its related dispatched events (`ProductStatesBeforeChangeEvent`, `ProductStatesChangedEvent`).
- Deprecate product stream filters and product listing filters that rely on `product.states`, guiding users to use the new `product.type` field instead.

### Introduce `product.type` field

Product type field should have a clear definition: It represents the type of product, whether it's physical or digital or bundle etc, and it should be immutable once set. A product can only have one type at a time.

In a more detailed manner, we will make the following changes:

- Add a dedicated `product.type` column (possible values by default: `physical` or `digital`) with DAL exposure, new entity constants, defaulting to `physical`.
- Also add `order_line_item.payload.product_type` and populate it when line items are converted from the cart; `LineItemTransformer` also reconstructs legacy states when needed.
- Introduce `LineItemProductTypeRule` for rule builder usage and deprecate the legacy `LineItemProductStatesRule`.
- Rules automatically pick up custom product types registered via the shared registry (@See `ProductTypeRegistry`), so PHP-based conditions stay consistent with storefront/admin filters.

#### Introduce a server-side `ProductTypeRegistry`

- This registry help both core rules and plugins can register additional product types via the parameter `%shopware.product.allowed_types%` as an array.

```php
class ProductTypeRegistry
{
    /**
     * @var array<string>
     */
    private array $types = [];

    public function addType(string $type): void

    public function getTypes(): array
```

- By default, the platform registers two types: `digital` and `physical`.

#### New admin API endpoint to fetch all registered product types

- Introduce a new admin api `GET /api/_action/product/types` to list all registered product types for use in admin UI for e.g product stream filters or Product listing filters

## Consequences

### For the platform
- Querying by digital/physical products now becomes trivial (`product.type = 'digital'`), improving DAL and search performance and clarity.
- The core will migrate existing `product.states` to `product.type` and the same from `order_line_item.states` to `order_line_item.payload.product_type` to preserve existing behavior.
- Rule conditions must be updated to reference `cartLineItemProductType`; existing rules referencing `cartLineItemProductStates` will continue to work until 6.8 but should be migrated.
- Similar to rule conditions, existing product stream filters must be updated to transition from `product.states` to the new `product.type` field.
- We should warn on the UI when users use `states` field in product streams, rule conditions, product listing filters, guiding them to use to the new `type` field instead.

### For third-party developers

- You can now easily register new product types by override `shopware.product.allowed_types` in your `config/packages/shopware.yaml`. For e.g:

```yaml
shopware:
    product:
        allowed_types:
        - bundle
        - container
```

- If you have existing code that relies on `product.states`, you should plan to migrate to the new `product.type` field.
- If you are creating digital products, you should explicitly set the `type` field to `digital` when creating new products.
- Be specific to use `type` field if you want to be safe to not have issues which fetching product types that you are not aware of. For examples, a third-party developer may introduce a new product type `container`, if you're not specific in your queries, you may incur unexpected results.
- Backwards compatibility must be maintained for 6.7, but in 6.8 the `states` fields should disappear entirely.
- Keep writing to the legacy `states` column only when `Feature::isActive('v6.8.0.0') === false`, wrapping all DAL fields, hydrators, and entity accessors in deprecation notices so tooling warns consumers.
