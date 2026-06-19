@README.md

## Constraints

- Loaders MUST return `ContentDataLoaderResult` — never throw exceptions
- DI tag: `content_system.data_loader`, indexed by `getRequirementType()` static method
- Each loader needs a config class + serializer pair (config serializer tag: `content_system.config_serializer`)
- Domain loaders registered in their owning module's DI, not in `content-system.xml`
- Use `$context->getContext()` for entity repository queries
- Built-in sources: `entity`, `entity_collection`, `product_listing`, `navigation`, `service_menu`, `cross_selling`, `product_review`, `product_search`, `product_suggest`, `breadcrumb`, `language`, `currency`, `payment_method`, `shipping_method`
- `@extends AbstractContentDataLoader<T>` PHPDoc annotation required — the base `producibleTypes()`/`resolveProducedType()` derive the produced type from it, and `ContentSystemDataLoaderTypeCompilerPass` dry-runs `extendsDescriptor()` at build time; missing or unresolvable annotation fails the build
- Wildcard loaders (`entity`, `entity_collection`) override `producibleTypes()` and `resolveProducedType()` — enumerate the live `DefinitionInstanceRegistry`, declaring the sales-channel class where a sales-channel definition exists, otherwise the base class
