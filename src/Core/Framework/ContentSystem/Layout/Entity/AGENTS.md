@.

## Source Code References

- `ContentLayoutEntity` - Layout template entity
- `ContentLayoutDefinition` - Layout DAL definition; `ENTITY_NAME = 'content_layout'`, `LAYOUT_FIELD = 'layout'`, `ROOT_SOURCE_FIELD = 'root_source'`; fields: `id`, `name` (string, 255), `version` (string, 20), `layout` (`ContentElementListField`, required), `root_source` (`StringField('root_source', 'rootSource')`, ApiAware + Required + Immutable — the layout's single declared root source: an entity type, a section, or `none`); product / category / landing-page assignment associations carry `RestrictDelete`
- `ContentLayoutCollection` - Layout collection

## Quick Reference

- Repository: `content_layout.repository`
- ID generation: `Uuid::randomHex()`
- Serialization: Automatic via custom field serializers in `Field/`
- Package: `#[Package('framework')]`
