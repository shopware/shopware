# Codemod data for Shopware 6.8

These files describe the Administration changes of Shopware 6.8 that extension code has to follow.
They are data only: the codemods read them and decide how to find and fix each usage.
The golden test in `scripts/extensionTooling/upgrade.spec/golden.spec.ts` defines the expected result.

## Common fields

Both files hold a list of entries, one per change:

- `id`: a unique name of the change.
- `deprecatedIn` and `removedIn`: the Shopware versions.
- `description`: what changed, for the report.
- `upgrade`: the `UPGRADE-6.8.md` headings that document the change.
- `usage`: the usages to find, one object per line.

Every usage has a `type`, and optionally a `message` and a `fix`.
`message` is the hint for the developer, it becomes the review comment.
`fix` is the fix level when it differs from the default of the type: `auto`, `unsafe-auto` or `manual`.

## `api-usage.json`

Usages of removed or changed APIs in scripts and templates.
`components` limits a usage to overrides and extensions of these components: `Component.override()`, `Component.extend()`, `<component>.override.vue` and the templates these overrides use, see `registry/component-scope.js`.

| `type` | Fields | Default fix | Finds |
|---|---|---|---|
| `member-usage` | `properties`, one of `object`, `service`, `store`, optional `withoutArgument`, `components` | manual | `<object>.<property>`, also destructured; `object: 'this'` also counts a bare name in templates, `*.name` any path ending in `name`; `withoutArgument` only calls that leave out that argument |
| `component-member` | `components`, `names` | manual | a member the components lost: `this.<name>`, its own definition in the options, its use in templates |
| `replace-component-member` | `components`, `name`, `with`, `templateMessage` | auto | rewrites `this.<name>` to `with` unless the override defines the member; template uses are reported with `templateMessage` |
| `replace-component-method-call` | `components`, `name`, `with`, optional `strictNullMessage` | auto | rewrites calls of a removed method, `{0}` in `with` is the first argument; a strict comparison with `null` is reported instead |
| `inject-key` | `names`, optional `components` | manual | an injected key |
| `removed-component` | `names`, optional `inTemplates` | manual | overrides and extensions of the components, and their elements unless `inTemplates` is false |
| `import-source` | `sources`, optional `specifiers` | manual | imports of the modules, matched as a whole or as the end of the path; type-only imports do not count |
| `string-literal` | `values`, optional `components` | manual | string literals with the value; object keys and import paths do not count |
| `object-key` | `names`, optional `files` | manual | keys of object literals, with `files` only in files whose base name matches that RegExp |
| `template-attribute` | `names`, optional `elements`, `components` | manual | attributes, prop bindings and listeners (`@name`) in templates |
| `module-snippets` | | auto | the `snippets` property of `Module.register()`, removed with its imports when the Administration loads every snippet file on its own |

## `template-blocks.json`

Overrides of removed and renamed Twig blocks, in `{% block %}` and `<sw-block extends>`.

| `type` | Fields | Default fix | Finds |
|---|---|---|---|
| `rename-template-block` | `from`, `to`, optional `component`, `extendedBy`, `removedSlotVariables` | auto | renames an override; an override that reads a variable of `removedSlotVariables` is reported instead |
| `remove-template-block` | `from`, optional `to`, `component`, `extendedBy` | manual | an override of a removed block; `to` is the block to move it to |

`component` limits a usage to the templates of that component and of the components in `extendedBy`, for block names that more than one component defines.
