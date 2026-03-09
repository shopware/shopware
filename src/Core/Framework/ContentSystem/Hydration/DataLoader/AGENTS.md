@README.md

## Constraints

- Loaders MUST return `ContentDataLoaderResult` — never throw exceptions
- DI tag: `content_system.data_loader`, indexed by `getRequirementType()` static method
- Each loader needs a config class + serializer pair (config serializer tag: `content_system.config_serializer`)
- Domain loaders registered in their owning module's DI, not in `content-system.xml`
- Use `$context->getContext()` for entity repository queries
- Built-in sources: `entity`, `entity_collection`, `product_listing`, `navigation`, `language`, `currency`, `payment_method`, `shipping_method`
