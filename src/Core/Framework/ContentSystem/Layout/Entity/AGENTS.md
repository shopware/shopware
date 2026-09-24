> Conceptual overview and design rationale live in the parent directory's
> [README.md](../README.md). The references and constraints below cover most code
> changes; read the README when you need the mental model.

## Source Code References

- `ContentLayoutEntity` - Layout template entity
- `ContentLayoutDefinition` - Layout DAL definition; `ENTITY_NAME = 'content_layout'`, `LAYOUT_FIELD = 'layout'`, `ROOT_SOURCE_FIELD = 'root_source'`; fields: `id`, `name` (string, 255), `version` (string, 20), `layout` (`StoredElementListField`, required), `root_source` (`StringField('root_source', 'rootSource')`, ApiAware + Required + Immutable — the layout's single declared root source: an entity type, a section, or `none`); product / category / landing-page assignment associations carry `RestrictDelete` — with the header and footer associations the Storefront contributes, that is all five, so a bound layout cannot be deleted: the DAL throws `RestrictDeleteViolationException` before any SQL runs, backed by `ON DELETE RESTRICT` on each `content_layout_id` foreign key. This is DAL machinery rather than a content-system gate, so `SKIP_VALIDATION_STATE` does not bypass it; the sibling `sales_channel_id` and `domain_id` keys stay `ON DELETE CASCADE`, so deleting a channel or domain removes the assignment while the layout survives
- `ContentLayoutCollection` - Layout collection

## Quick Reference

- Repository: `content_layout.repository`
- ID generation: `Uuid::randomHex()`
- Serialization: Automatic via custom field serializers in `Field/`
- Package: `#[Package('framework')]`
