# 6.9.0.0

# Core

## Document generation v1 removed

The legacy document generation implementation was removed together with the `DOCUMENT_GENERATION_REWORK` feature flag. Document generation v2 is now the only implementation. The full strategy is described in the [migration ADR](adr/2026-08-05-document-generation-v1-to-v2-migration-strategy.md).

The complete list of removed classes, entities, and Administration components is in `UPGRADE-6.7.md` ("Document generation v1 marked for replacement", section 6.7.15.0). In addition:

- The `document.renderer` and `document_type.renderer` service tags were removed. Register document types, data providers, and renderers via the `shopware.document_v2.type`, `shopware.document_v2.provider`, and `shopware.document_v2.renderer` tags instead, or use the app manifest `<documents>` block. See the [extension points guide](https://developer.shopware.com/docs/concepts/commerce/checkout-concept/document/extension-points.html).
- The `document_type` and `document_type_translation` entities were removed including their DAL definitions and associations. Document types are code-registered strings. Read the type from `document.typeName` instead of the `documentType` association. Persisted references were backfilled into the `type_name` columns, and the `document_type_id` columns became nullable.

The class aliases and deprecated service aliases of the shared document classes were removed. These classes moved into `Shopware\Core\Checkout\DocumentV2` with 6.7.16.0 (see "Shared document classes moved to `DocumentV2`" in `UPGRADE-6.7.md`). References to a previous name, for example `Shopware\Core\Checkout\Document\DocumentEntity`, no longer resolve. Use the canonical names in imports, type declarations, static references, and service IDs.

Twig template overrides were not affected: v2 renders the same `@Framework/documents/*.html.twig` templates.

Already generated documents stay accessible without regeneration. Migrations backfilled the required file metadata (`document_file` rows) and normalized legacy ZUGFeRD document types into their base type plus a ZUGFeRD format file.

## Migrating document extensions from v1 to v2

Every v1 extension point has a v2 counterpart:

| I used (v1) | Use instead (v2) |
|---|---|
| Custom document type: `AbstractDocumentRenderer` + `document.renderer` tag + a `document_type` row | A type class + data provider + Twig template, or the app manifest `<documents>` block (see below) |
| `InvoiceOrdersEvent`, `DeliveryNoteOrdersEvent`, `CreditNoteOrdersEvent`, `StornoOrdersEvent`, `ZugferdCreditNoteOrdersEvent`, `ZugferdCancellationInvoiceOrdersEvent` to add or change data before rendering | An additional data provider for that document type (see below) |
| `DocumentOrderCriteriaEvent` to load extra order associations | `AbstractDocumentDataProvider::enrichOrderCriteria()` |
| `DocumentTemplateRendererParameterEvent` to add Twig variables | A data provider. The public properties of its render data DTO become template variables on `config` |
| Decorating `DocumentGenerator` | Not carried over, the v2 orchestration is internal. Move the logic into a data provider or renderer, or react to the `document.generation.completed` / `document.generation.deleted` events |
| Overriding `@Framework/documents/*.html.twig` templates | Unchanged. v2 renders the same templates, one per type at `@Framework/documents/<technical_name>.html.twig` |
| Reading `document_type` via the DAL | The type string on `document.typeName`. The available types are exposed via `GET /api/_action/order/document-v2/available-types` |

### I registered a custom document type

Register a type class and a data provider as tagged services, and ship a Twig template named after the technical name. Templates are resolved by convention. A database row is no longer needed:

```php
readonly class WarrantyDocumentType extends AbstractDocumentType // tag: shopware.document_v2.type
{
    public function getTechnicalName(): string { return 'swag_warranty'; }
    public function getSupportedFormats(): array { return ['html', 'pdf']; }
}

readonly class WarrantyDataProvider extends AbstractDocumentDataProvider // tag: shopware.document_v2.provider
{
    public function getKey(): string { return 'swag_warranty'; }
    public function supports(string $documentType): bool { return $documentType === 'swag_warranty'; }
    public function provideRenderingData(ProviderInput $input, Context $context): AbstractRenderData { /* ... */ }
}
```

Template: `Resources/views/documents/swag_warranty.html.twig`. Apps achieve the same declaratively via the manifest `<documents>` block.

### I enriched an existing type with additional data

Register an additional data provider that supports the existing type with its own unique key. All providers matching a type run once per generation, and every format is rendered from the combined data:

```php
readonly class WarrantyInfoProvider extends AbstractDocumentDataProvider
{
    public function getKey(): string { return 'swag_warranty_info'; }
    public function supports(string $documentType): bool { return $documentType === 'invoice'; }

    public function enrichOrderCriteria(Criteria $criteria): void
    {
        $criteria->addAssociation('lineItems.product.manufacturer');
    }

    public function provideRenderingData(ProviderInput $input, Context $context): AbstractRenderData
    {
        return new WarrantyInfoRenderData(/* ... */);
    }
}
```

The public properties of the returned DTO are available in the document templates through the `config` variable. Apps enrich data through the `document-generation` script hook instead.

### I rendered a custom output format

Implement `Shopware\Core\Checkout\DocumentV2\Renderer\AbstractDocumentRenderer` and register it with the `shopware.document_v2.renderer` tag. One renderer produces exactly one format and receives the shared, provider-prepared `RenderInput`.

## Changed API alias of `RenderedDocument`

`Shopware\Core\Checkout\DocumentV2\Struct\RenderedDocument::getApiAlias()` returned `shopware_core_checkout_document_v2_struct_rendered_document` instead of `shopware_core_checkout_document_renderer_rendered_document`. Consumers that matched serialized data on the previous alias had to switch to the new one.

# Administration

## Legacy document components removed

The legacy document services and components in the Administration were removed:

- `DocumentApiService` (including `DocumentEvents`). Use the v2 Admin API routes (`/api/_action/order/document-v2/*`) instead.
- The legacy document modals and their component registrations: `sw-order-document-settings-modal`, `sw-order-document-settings-invoice-modal`, `sw-order-document-settings-credit-note-modal`, `sw-order-document-settings-delivery-note-modal`, `sw-order-document-settings-storno-modal`, and `sw-order-select-document-type-modal`.

# Storefront

## Removed `category_url` and `category_linknewtab` Twig functions

The `category_url` and `category_linknewtab` Twig functions have been removed.
Use `category.seoUrl` instead of `category_url` and `category.shouldOpenInNewTab` instead of `category_linknewtab`.

The `seoUrl` property is only available on `SalesChannelCategoryEntity`. If a template receives a plain `CategoryEntity`, update the data-loading code to load categories in the sales channel context and pass `SalesChannelCategoryEntity` instances to the template.

```diff
<a class="link"
-   href="{{ category_url(item) }}"
+   href="{{ item.seoUrl }}"
-   {% if category_linknewtab(item) %}target="_blank"{% endif %}
+   {% if item.shouldOpenInNewTab %}target="_blank"{% endif %}
</a>
```
