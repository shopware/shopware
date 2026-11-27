# Public API snapshot

> Status: In-Progress

The Administration gets a generated snapshot of its public API and a check that blocks unannounced breaks, see [#22591](https://github.com/shopware/shopware/issues/22591).
Both are built in steps.
Five parts exist so far: the snapshot of the components with their blocks and members, the snapshot of the services, the markers that announce a change, the break check for both, and the fixtures that the generator and the break check have to satisfy.
The grammar of the markers is in `eslint-rules/deprecation-rules/utils/api-markers.ts`, the code of the generator in `scripts/generate-public-api/`.

## Snapshot

The snapshot is committed in `public-api/`.
It lists every component that core registers, with the members its options object declares and the blocks of the template it renders.
It also lists every service that core registers, with the properties and methods of the object behind it.
A pull request that adds, renames, moves or removes a block, a prop, a method or a service therefore shows that change in its diff.

Regenerate it after such a change and commit the result:

```bash
composer admin:generate-public-api
```

`scripts/generate-public-api/snapshot/committed-snapshot.spec.ts` fails while the committed files differ from the sources.
When a block of the committed snapshot is gone from the templates, it reports that as a breaking change and names the block.
Regenerating the snapshot makes both tests pass, because they compare files and judge no change.
The break check judges a change, see "Break check".
It runs on demand so far and blocks nothing.

### Files

A component has one report, named after the component and placed beside its folder, without the leading `src/`.
`src/module/sw-order/page/sw-order-detail/index.js` gives `public-api/module/sw-order/page/sw-order-detail.api.md`.

The services of a source file share one report, named after that file without the leading `src/`.
`src/core/service/api/cache.api.service.js` gives `public-api/core/service/api/cache.api.service.api.md`.

| File | Content |
|---|---|
| `<name>.api.md` | The report for reviewers, which merges both flag states |
| `index.json` | The components with their file, visibility, parent and the names of the blocks they render, and the services with their file and visibility, for tools such as ESLint rules |

The `.d.ts` contracts follow once they can be type-checked, see "Not covered yet".
All files are marked as generated, so GitHub collapses them in a diff.
Names and lines are sorted by code point, and the files contain no line numbers, so a change to a template only changes the lines of the blocks it touches.

```text
component sw-example-field-set  public  js
  registered src/app/component/index.ts
  source src/app/component/form/sw-example-field-set/index.js
  template src/app/component/form/sw-example-field-set/sw-example-field-set.html.twig

blocks
  sw_example_field_set
    sw_example_field_set_content
    sw_example_field_set_mt_tabs  [from v6.8.0]
    sw_example_field_set_tabs  [until v6.8.0]
```

- The report shows the blocks as a tree, two spaces per level, with the blocks of one parent sorted by name.
- A block that the template declares under several parents is listed under each of them.
- Twig blocks and `<sw-block name="...">` elements share one tree.
  A `.vue` component is found through the file that re-exports it, or through a registration that imports the `.vue` file directly.
  A bound `:name` has no literal name and is left out.

### Extend children and core overrides

The report of a component shows the template it renders.
For an extend child that includes the blocks it inherits, so a block change in a parent changes the reports of its extend children too.
Each block of an extend child carries its origin as the first marker:

| Marker | Meaning |
|---|---|
| `[inherited]` | Taken unchanged from the component it extends |
| `[replaced]` | Redeclared without `{% parent %}`, so the blocks inside the parent block are gone |
| `[keepsParent]` | Redeclared with `{% parent %}` |
| none | A block the child adds inside a block it redeclares |

```text
component sw-example-tile  public  js
  extends sw-example-card
  ...

blocks
  sw_example_card  [inherited]
    sw_example_card_content  [inherited]
      sw_example_card_text  [replaced]
    sw_example_card_header  [keepsParent]
      sw_example_card_title  [inherited]
      sw_example_tile_badge
```

The origin is relative to the direct parent: a grandchild marks a block `[inherited]` whatever its parent did with it.
The inherited blocks of a public child are public, also when the parent is private.
A child is limited to the flag states in which every component of its chain is registered.

The generator merges the templates by the rules of `src/core/factory/template.factory.js`, which differ from plain Twig:

- Only the top-level blocks of a child template replace blocks of the parent.
  A block inside them is a new block at that place, even when the parent has a block of that name elsewhere.
- A top-level block that the parent does not have is dropped, and the blocks directly inside it count as top-level blocks.
  This lets a child wrap its template in a block of its own, which is then no block of the component.
- A redeclaration replaces the whole block.
  `{% parent %}` brings the parent content back, directly in the redeclaration or one block deeper.
- Below a block that a child redeclares, the same template cannot redeclare another block, with or without `{% parent %}`.
- A block the child declares twice uses the first declaration.
  A block the parent has several times is replaced in every place, and `{% parent %}` takes the content of the first one.
- The flag state of a block comes from the place where it renders.
  A `v-if` around a top-level block of a child template has no effect, because only the content of that block is used.

A core `Component.override` has no report of its own yet.
Its template is applied to the component it overrides, as at runtime, so the blocks it adds or removes show up in the report of that component and of its extend children.
Core overrides apply before a child extends the component, ordered by their index.
The runtime applies overrides with the same index in the order they are registered, which the generator cannot know, so it takes them in the order of their file paths.
Unlike an extend template, each top-level block of an override is applied on its own, so a later block can replace one that an earlier block kept or added.

Some blocks of an extend or override template never render: the runtime drops a block that replaces nothing, without a warning.
Such a block is in no report, and `scripts/generate-public-api/read/template/extend-chain.spec/runtime.spec.ts` fails when a template declares one.
The same spec registers every template with the runtime factory and compares the blocks of each component with the snapshot, their origin included.

### Block structure

An override replaces the content of a block, but it runs in the place of the block.
The report shows what that place offers, and which elements make a block unsafe to replace.

```text
blocks
  sw_example_card
    sw_example_card_empty  [after v-if]
    sw_example_card_item  [scope: index, item]

crossings
  <div>  sw_example_card_header -> sw_example_card_footer
  v-if  sw_example_card_title -> sw_example_card_fallback
```

| Marker or line | Meaning |
|---|---|
| `[after v-if]` | The block follows an element with `v-if` or `v-else-if`, so an override may start with `v-else-if` or `v-else` |
| `[scope: <names>]` | The variables of the `v-for` and slot elements around the block, which an override can use |
| `<tag>  <a> -> <b>` | An element opens in block `a` and closes in block `b`, so replacing one of them leaves a tag without its partner |
| `v-if  <a> -> <b>` | A `v-if` chain of block `a` continues in block `b`, so replacing `a` leaves a `v-else` without its `v-if` |

- Only whitespace, comments and the tags of other blocks may stand between the element and the block, as in Vue.
  A `v-else` closes the chain.
- A conditional slot, `<template v-if="..." #name>`, counts for no block: Vue continues it with another slot only.
- A block has the variables of the blocks around it.
  A block the template declares in places with a different context has one line per context.
- `(root)` stands for the template outside of every block.
- A chain is no crossing when the block of the earlier member holds the block of the later one.
- The origin of an extend child comes first, then `[after v-if]`, `[scope: ...]` and the flag state.

The structure is read from the template a component renders, after the merge along its extend chain.
An inherited block has the context of its place in the parent, and a block the child adds has the context of the place it renders in.
`scripts/generate-public-api/read/template/block-structure.spec/compiler.spec.ts` asks the Vue compiler the same questions about every block of the Administration and compares the answers.

The generator also keeps the conditions of the chain a block follows, whether the content of the block continues that chain, and where each variable comes from, for example `v-for products` or `slot sw-data-grid#actions`.
The break check compares them, see "Break check".
The files do not contain them, so an edit to a condition changes no file.

### Members

The report lists what a plugin reaches besides the blocks: the members of the options object, and the slots and template refs of the template.
CSS classes are no public API, so the report does not list them.

```text
mixins
  notification, listing

inject
  acl
  factory from repositoryFactory

props
  entity: string
  value?: string  default='medium'

emits
  update:value(...args: any[])

slots
  footer  { item }  block=sw_example_card_footer

data
  pagination: object
    page: any

computed
  hasTitle: any

methods
  onDeleteItem(item: any, itemIndex?: any): any  [bc-change ParameterRemoval v6.8.0 itemIndex]
  onSave(): Promise<any>  [deprecated tag:v6.8.0]

refs
  grid  -> sw-data-grid  block=sw_example_card_grid
  rows  -> sw-data-grid-row  list  block=sw_example_card_grid

notes
  unresolved computed ...mapPageErrors(errorConfig)
```

| Section | A line holds |
|---|---|
| `mixins` | The registered mixins on one line, in the order the component applies them |
| `inject` | The local name, and the injected key when it differs |
| `provide` | The provided key |
| `props` | The name with `?` for an optional prop, its type, and its default as the source writes it, or `default=factory` |
| `emits` | The event with the parameters of its validator, `...args: any[]` for the array form |
| `slots` | The name, the props the slot passes, and the block around it |
| `data` | The key, and the keys of a nested object literal indented below it |
| `computed` | The name and the type its getter declares |
| `methods` | The signature. A parameter with a default is optional and shows its default, a destructured one is named `arg<position>`, and a method that returns a promise returns `Promise<any>` |
| `refs` | The ref, the tag it sits on, with `list` for a ref inside a `v-for`, and the block around it |
| `notes` | What the generator could not read, see below |

The sections stand in this order, with `blocks` and `crossings` between `methods` and `refs`.

- A component lists its own members.
  The members of its mixins and of the component it extends are in their units.
- Only the syntax is read, so code inside a method changes nothing.
  A JavaScript member is `any`.
  A TypeScript member keeps the text of its annotation, and a prop the type argument of `PropType<...>`.
- The prop constructors map to `string`, `number`, `boolean`, `object`, `any[]` and `(...args: any[]) => any`.
  Any other constructor, such as `Criteria`, keeps its name, and `null` in a list of constructors is `null`.
  A list of constructors is printed in code point order, so reordering it changes nothing.
- A method returns a promise when it is `async`, declares a `Promise` return type, or every `return` hands back a promise the source builds: a `.then()`, `.catch()` or `.finally()` chain, a `Promise.*()` call or `new Promise()`.
  A plain call such as `this.repository.save()` may return a promise too, but only the type checker knows, so it does not count.
  A method returns no promise when it declares a type such as `void` or `string[]`, or when every `return` hands back nothing or a value the source builds, such as a literal, an object, an array or a comparison.
- The options object is found behind `wrapComponentConfig`, `defineComponent` and a `const` of the same file.
- `mapPropertyErrors`, `mapCollectionPropertyErrors`, `mapState`, `mapGetters`, `mapWritableState`, `mapActions` and `mapMutations` add the members they list as literals.
  A method keeps its parameters when a helper such as `debounce` wraps the function.
- Slots and refs are read from the template after the merge along the extend chain, so an extend child lists what its parent renders too.
  A slot or a ref in two blocks has one line per block, because a plugin replaces it when it overrides that block.
- A marker in the comment directly above a member follows its line: `[deprecated tag:vX]`, `[bc-change <Kind> vX]` with the parameter it names, and `[private]`.
  The comment can be a JSDoc comment or a `//` line.
  A block shows its markers the same way, behind its context.

A note says that a section may miss members, with the start of the source the generator could not read:

| Note | Meaning |
|---|---|
| `unresolved <section> <source>` | A spread, a computed key or a name that is no literal in that section |
| `unresolved options <source>` | A spread into the options object, a `setup` function, or a source without an options object, such as a native component |
| `dynamic-ref <expression>`, `dynamic-slot <expression>` | A bound `:ref` or a `<slot :name>` |

`scripts/generate-public-api/read/options/option-members.spec/runtime.spec.ts` loads every component of the Administration and compares the names with the options object at runtime.
A section with a note may hold fewer names than the runtime, every other section has to hold the same.

### Services

A service is the object that `Shopware.Service('<name>')` returns.
The generator finds a service by its registration and reads the object behind it from the syntax, without running it.

```text
service exampleSearchService  public  js  untyped  provider
  registered src/module/sw-example/init/services.init.js
  source src/module/sw-example/service/example-search.service.js

properties
  maxResults: any

methods
  getFields(entity: any, forceReload: any): any
  reset(): Promise<any>  [deprecated tag:v6.8.0]
  search(term: any, limit?: any = 25): any
```

- The header line names the service, its visibility, the language of its source, what the `ServiceContainer` interface declares for the name, and how the service is registered.
  `typed` is an entry with a type, `any` an entry with `$TSFixMe` or `any`, and `untyped` a name without an entry.
- `provider` is a call of `addServiceProvider('<name>', <provider>)` or of `Shopware.Service().register('<name>', <provider>)`.
  `api-service` is a class below `src/core/service/api/`.
  The boot sequence creates one instance per file there and registers it under the name the constructor assigns to `this.name`.
- The generator follows the provider to the object: through `new`, through the call of a factory function, and through names and imports, down to a class or an object literal.
  `source` is the file of that class or literal.
  An object that the provider writes itself has the file of the registration as its source.
- A class gives its methods, properties and getters, what its constructor assigns to `this`, and what it inherits along its `extends` chain.
  `private`, `protected`, `#private` and static members are left out, because code that holds the service cannot reach them.
- An object literal gives its keys.
  A key that names a function of the same file, as in `return { search }`, has the signature and the comment of that function.
- A method is printed like the method of a component, and a property with the type the source declares for it.
- A service that is a function has no members, and neither has one whose provider returns nothing.
- Core registers some services twice with the same object, which is one service in the snapshot.

What the generator cannot follow becomes a line under `notes`, and the service stays in the snapshot with its name:

| Note | Reason |
|---|---|
| `unresolved provider ...` | The provider returns something the generator cannot follow, such as an instance of a class it reads from `Shopware.Classes` |
| `unresolved parent ...` | The class extends one that the sources do not declare under that name |
| `unresolved members ...` | An object literal spreads something that is no object literal, or has a computed key |

The Administration has 10 of these notes today.

### Flag states

The snapshot covers two flag states: `current`, with the next major flag off, and `major`, with that flag and every flag that names it as its `major` in `feature.yaml` on.
A break behind the major flag would be invisible in a single state.

- A block inside a `v-if`, `v-else-if` or `v-else` branch that only one state renders exists in that state only.
  The report marks it `[until v6.8.0]` or `[from v6.8.0]`.
  The marker names the release, so it stays readable in old commits.
- Only `feature.isActive('<flag>')` with `!`, `&&` and `||` is evaluated, in both spellings of a flag, `v6.8.0.0` and `V6_8_0_0`.
  Any other condition can be true in both states.
- A component that is registered inside `if (!Shopware.Feature.isActive('v6.8.0.0'))` exists in the current state only.
  Its report marks the `component` line and its blocks `[until v6.8.0]`.
  The same holds for a service and its members.

### Visibility

Everything without `@private` is public.
A component is private when the comment directly above its `export default`, or directly above its registration, has an `@private` tag.
A private component stays in the snapshot, and its report marks every line `[private]`.
A key inside a `@private` data object is private too.
A service is private when the comment directly above its class, or above the factory function that returns it, has an `@private` tag.
The comment above an `export default` of that class or function below it counts too.
A `@deprecated` or `@bc-change` marker in the same comment is the marker of the service.

### Not covered yet

- A core `Component.override` adds no entry of its own, and the report does not say which blocks come from one.
- A component that keeps its blocks in a `.vue` file cannot be extended, and cannot be overridden with a Twig template.
- A native component declares its members in `<script setup>`, which is not read yet.
  Its report has the note `unresolved options` instead.
- A mixin has no unit of its own, so a member that a mixin loses is not reported.
- The members of a core `Component.override` are missing.
- The keys of `data()` have no types.
- A decorator or a middleware that core adds to a service changes its members at runtime and is not read.
- A service whose provider reads its class from the `Shopware` object has no members, see its note.
  The same holds for the members a service inherits from such a class.
- The types of a service are those the source writes, so an inferred type is `any`.
- No spec compares the services with the container at runtime, as `option-members.spec/runtime.spec.ts` does for the options of a component.
- The `.d.ts` contracts are not generated yet.
  They arrive together with the entry points `current.d.ts` and `major.d.ts`, so they can be type-checked from the start.
  Until then, the fixture views `block-contract` and `member-contract` are on the expected-fail list.

The generator stops with an error when it cannot read something:

- a block in a template that no registered component or core override uses
- a block or `{% parent %}` tag in a place the HTML parser cannot see
- a registration or override whose component name, import or index it cannot read
- a component name that is registered twice
- an extend chain that names an unregistered component, loops, or has a child with blocks below a parent without a Twig template
- a core override of an unregistered component, or one that is registered in one flag state only
- a service registration whose name is no string literal, outside `src/app/init-pre/api-services.init.ts` and `src/core/factory/service.factory.ts`, which pass a service on under a name they get at runtime
- a file below `src/core/service/api/` whose default export is no class with a literal `this.name`, or an `index.ts` there that loads the files with another glob
- a service name that two registrations give to two different objects

## Markers

A marker is a comment on the element it announces a change for.
`@deprecated` is for an element that is removed or replaced, so plugins can migrate now.
`@bc-change` is for a planned change to an element that stays.
It is a tag of its own because the extension ESLint preset reports every use of a `@deprecated` element as an error, and here plugins have nothing to migrate to.

```text
@deprecated tag:vX.Y.Z - <text>
@bc-change <Kind> version:vX.Y.Z [<key>:<value> ...]
  <text>
```

```js
/**
 * @bc-change ParameterRemoval version:v6.8.0 parameter:itemIndex
 *   The template stops passing the index. Use this.lineItems.indexOf(item).
 */
onDeleteItem(item, itemIndex) {
```

```twig
{# @bc-change BlockMoved version:v6.8.0 parent:sw_order_detail_state_card_state_select #}
{% block sw_order_detail_state_card_state_history_text %}
```

- A tag counts when it starts a line of a JSDoc comment, a line comment, a Twig comment or an HTML comment.
  A mention inside a sentence is not a marker.
- No punctuation follows the tag name, so `@deprecated:` and `@deprecated,` are reported.
- The version has three parts, as in `coding-guidelines/core/backward-compatibility.md`.
- A value ends at the next space, so it contains none.
  Write a type without spaces, as in `type:string|null`.
- The text of a `@deprecated` is required and follows the version after `- ` on the same line.
- The text of a `@bc-change` starts on the line after the marker.

| Kind | Announces | Keys |
|---|---|---|
| `ParameterRemoval` | A method loses a parameter | `parameter` |
| `NewRequiredParameter` | A method gets a required parameter, or an optional one becomes required | `parameter` |
| `ParameterDefaultValueChange` | The default value of a parameter changes | `parameter`, `default` |
| `ReturnTypeNarrowing` | A method or computed property returns a narrower type | `type` |
| `ReturnTypeWidening` | A method or computed property returns a wider type | `type` |
| `BecomesPrivate` | A public element becomes `@private` | none |
| `BlockMoved` | A block moves under another parent block | `parent` |
| `ScopeVariableRemoval` | A block loses a template variable from a `v-for` or a slot | `variable` |
| `PropBecomesRequired` | An optional prop becomes required | none |
| `PropDefaultValueChange` | The default value of a prop changes | `default` |
| `EmitPayloadChange` | The payload of an event changes | none |
| `RouteParentChange` | A route moves under another parent route, or to the top level | `parent` |
| `RegistryGroupChange` | A registry entry, for example a rule condition, moves to another group | `group` |

The first five kinds have the names of the PHP attributes in `src/Core/Framework/Deprecation/BCChange/`.
Their keys are shorter than the PHP arguments, for example `parameter` for `parameterName` and `type` for `newType`.
`BecomesPrivate` is what PHP calls `BecomesInternal`.
The other kinds exist only in the Administration.
The break check does not use `EmitPayloadChange`, `RouteParentChange` and `RegistryGroupChange` yet.
They can still change until it uses them.

`eslint-rules/deprecation-rules/utils/api-markers.ts` holds the grammar and the kinds.
The ESLint rule `sw-deprecation-rules/valid-api-markers` uses it and reports, in `.js`, `.ts`, `.vue` and `.html.twig` files:

- a typo in the tag name one edit away, such as `@bc-chnage` or `@deprecate`
- a missing or malformed version
- an unknown kind
- a missing key, or a key the kind does not take

Without the rule such a comment would stay a plain comment, and the change would surface as an unannounced break in the major.

The PHP test `AnnotationTagTester` checks the version of `@deprecated` in every file of the repository as well, with its own grammar.
A change to the `@deprecated` grammar has to be made in both places.

The rule checks the format only.
The break check reads the markers of a block and decides with them whether a change is announced, see "Break check".
Nothing reports a marker on an element its kind does not fit, or a marker whose version is released already.

## Break check

The break check compares the snapshot of a base commit with that of a head commit and reports what changed for plugins.
It generates both snapshots with the generator and the major flags of the checkout, and it ignores the committed files.

```bash
composer admin:check-public-api -- <base> <head>
```

The command prints the summary and fails when the change has unannounced breaks.
A commit can hold templates that no component uses.
The generator rejects those for the committed snapshot, and the check leaves them out, because it prints no snapshot.

### Rules for blocks

`break-check/rules/block-rules.ts` holds one rule per kind of change.
A rule compares the blocks of one component in one flag state, and its text says what an override of the block notices.

| Rule | Group | Reported when | An override notices |
|---|---|---|---|
| `block_missing` | Break | A block is gone | It replaces nothing and renders nothing |
| `block_moved_parent` | Break | A block left a block that still exists, or entered one that existed before | Overrides of that block without `{% parent %}` render without it or drop it |
| `element_crosses_block` | Break | A new element crossing | Replacing either block leaves a tag without its partner |
| `if_chain_crosses_block` | Break | A new `v-if` crossing | Replacing the first block leaves a `v-else` without its `v-if` |
| `block_if_context_lost` | Break | A block lost `[after v-if]`, and its content continued the chain | An override that starts with `v-else-if` or `v-else`, as the content of the block did, no longer compiles |
| `block_scope_missing` | Break | A block lost a variable | An override that uses the variable fails at render time |
| `block_added` | Addition | A new block | Nothing, plugins can rely on it from now on |
| `block_if_neighbour_lost` | Info | A block lost `[after v-if]`, and its content did not continue the chain | Nothing, unless it starts with `v-else-if` or `v-else` on its own |
| `block_if_chain_changed` | Info | The conditions of the chain before a block changed | Its `v-else` may render under another condition |
| `block_scope_source_changed` | Info | A variable comes from another `v-for` expression or slot | The variable may hold another value |
| `crossing_resolved` | Info | A crossing is gone | Nothing |

- A block that only got a new block around it did not move, and neither did one whose parent is gone: every existing override renders as before.
  The blocks inside a moved block are not reported.
- A crossing that the base commit has already is no finding.
- A block continues a chain when its content starts with the `v-else-if` or `v-else` of that chain.
  Plugins copy that into their override, so losing the chain breaks them.
  Most blocks with `[after v-if]` only stand behind a conditional element, and an override has no reason to continue that chain.
  In the history of the Administration this second case is far more common, and reporting it as a break would mostly raise false alarms.
- `block_if_chain_changed` and `block_scope_source_changed` compare text.
  A changed condition can be the same condition under a new name, so they never fail the check.
- A removed block with exactly one new block under the same parent gets a `Possible rename`.
- Only components that both commits register are compared, in the flag states both register them in.
  A component that comes or goes is not reported yet, and neither is a component that becomes `@private`.

### Rules for members

`break-check/rules/member-rules.ts` builds one rule per kind of member for a member that is gone and one for a new member, from the table of kinds in `model/members.ts`.
The rules below those are about what only one kind has.

| Rule | Group | Reported when | A plugin notices |
|---|---|---|---|
| `<kind>_missing` | Break | A mixin, injection, provided value, prop, event, slot, data key, computed property, method or template ref is gone | Its override reads `undefined`, or `$super` throws |
| `<kind>_added` | Addition | A new member, except a ref | Nothing, plugins can rely on it from now on |
| `prop_now_required` | Break | An optional prop became required | Templates that do not pass it fail prop validation |
| `param_missing` | Break | A method lost a parameter | Overrides that read the parameter get `undefined`, and the parameters behind it receive the wrong values |
| `param_now_required` | Break | A new parameter without a default behind the existing ones, or a parameter that lost its default | Calls with the old arguments leave it `undefined` |
| `param_added` | Addition | A new optional or rest parameter behind the existing ones | `$super` calls with explicit arguments do not pass it on |
| `method_signature_changed` | Break | A method starts or stops returning a promise, its parameters changed their order, or a new parameter stands in front of an existing one | The return value is a Promise, or values arrive in the wrong place |
| `method_param_renamed` | Info | A parameter has another name at the same position | Nothing |
| `ref_target_changed` | Break | A ref sits on another tag, or moved into or out of a `v-for` | Methods it calls on the ref are gone |
| `type_changed` | Break | The declared type of a prop or of a property changed | Values it passes or reads may no longer fit |
| `return_type_changed` | Break | The declared type of a computed property or the return type of a method changed | Code that uses the value may no longer fit |
| `type_widened` | Info | An untyped member got a type, a typed one lost it, or a prop got a `PropType` on the constructors it had | Nothing |
| `prop_default_changed` | Break | An optional prop lost its default, or its default value changed | Templates that do not pass it get `undefined` or another value |
| `param_default_changed` | Break | An optional parameter lost its default, or its default value changed | Calls that do not pass it get `undefined` or another value |
| `visibility_became_private` | Break | A member of the options object became `@private` | Its code may change in any release |
| `deprecation_added`, `bc_change_added` | Info | A member got a new marker | Nothing yet, the upgrade notes should name it |

- Parameters are matched by name, and a destructured one by its pattern, because a caller passes them by position.
  A finding names a destructured parameter by its pattern too, for example `method:load/param:{id}`, which a marker writes as `parameter:{id}`.
  A parameter that keeps its name but stands at another position receives the value meant for another one.
  A parameter with a new name at the same position is a rename, and calls pass it the same value.
  A method that is no function in the source, such as an imported one, has no parameters to compare.
- `type_changed` and `return_type_changed` compare the text of the declaration.
  Whether another type still fits needs the type checker, so every other text fails the check until a reviewer has checked it.
  A method with two declared return types is compared by `return_type_changed` only, also when one of them is a `Promise`.
- A method starts or stops returning a promise only when the source shows it on both sides.
  `return this.httpClient.get(...)` that gains a `.then()` or becomes `async` is no finding, because the call may already return a promise.
- A `PropType` on the constructors a prop had is `type_widened`, because Vue checks a value against the same constructors as before.
  Another constructor or another `PropType` is `type_changed`.
- The defaults compare the text of the value, also when it names a `const` of the same file.
  A factory has no value to compare, so only a factory that goes away is reported.
- A nested data key follows its object.
  An object that comes or goes is reported once, without its keys, and a key that goes away is announced by a marker on an object around it too.
- An extend child is compared with the members it inherits along its extend chain.
  An override that the child drops is no change, and a member that the parent loses is reported once, on the parent.
  A method that only returns `this.$super()` of itself also returns a promise when the method it overrides does.
  A data key of the child replaces the whole object it inherits under that key.
- A removed member with exactly one new member of the same kind that differs in the name only gets a `Possible rename`.
- A removed member has no line in the head commit.
  The finding points to the option that held it, and for a slot or a ref to the closest block around it that is left.
- The id of a member is `<kind>:<name>`, with the kinds `mixin`, `inject`, `provide`, `prop`, `emit`, `slot`, `data`, `computed`, `property`, `method` and `ref`.
  A nested data key is named by its path, `data:pagination.limit`, and a parameter below its method, `method:onDeleteItem/param:itemIndex`.
- The arguments of an event and the props of a slot are not compared yet.
- The break check does not validate markers yet: a `@bc-change` that names a parameter the method does not have is reported as `bc_change_added`.

### Rules for services

The rules for members apply to the properties and methods of a service too, with the kinds `property` and `method`.
A finding about a service says how plugin code reaches the member, `Shopware.Service('<name>').<member>`, where a finding about a component names `$super`.
`break-check/plugin-use.ts` holds both wordings.

`break-check/unit-changes.ts` reports a service as a whole:

| Rule | Group | Reported when | A plugin notices |
|---|---|---|---|
| `service_missing` | Break | A service is gone, in both flag states or in one | `Shopware.Service('<name>')` returns `undefined` |
| `service_added` | Addition | A new service | Nothing, plugins can rely on it from now on |
| `visibility_became_private` | Break | A public service became `@private` | Its code may change in any release |

- A `@deprecated` marker on the service announces `service_missing`, and `@bc-change BecomesPrivate` announces `visibility_became_private`.
- A service that is private in the base commit, and a new private one, are listed as `Private`.
- A removed service has no line in the head commit, so its finding has none.
  The finding about a removed member points to the class or object literal of the service.
- A service is compared with the members it inherits from its parent classes.
  A change that several services share through a class is reported once, on the class closest to the root of the chain, as in `class:ApiService/property:httpClient`, and the finding lists the services under `Also affects`.
  Its text names the services as `<instance of ApiService>`.
  A change that only one service has stays on the service, and so does a change to a service that overrides the member.
  A class has no entry in the snapshot. Two classes of one name report nothing, and their services keep their own findings.
- The id of a service is `service:<name>`, and a member stands below it, as in `service:acl/method:can`.
- A marker that a service gets is not reported yet, while one on a member is `deprecation_added` or `bc_change_added`.

### Groups

The rule of a finding names the change, and its group says what the change counts as.

| Group | Meaning |
|---|---|
| `Break` | An unannounced break. Only this group fails the check |
| `Addition` | New public API |
| `Allowed` | A change that a marker on the block or the member announced |
| `Private` | A change to a component or service that is `@private` in the base commit, or to a `@private` member of a public one |
| `Info` | A change that a reviewer may want to look at |

A marker announces a change when it sits in the Twig comments directly before the `{% block %}` tag, or in the comment directly above the member:

| Marker | Announces | Rule of the finding |
|---|---|---|
| `@deprecated tag:vX.Y.Z` | `block_missing`, `<kind>_missing` | `deprecated_removal` |
| `@deprecated tag:vX.Y.Z` on a block, for the blocks inside it and for a slot or a ref inside it in every place | `block_missing`, `slot_missing`, `ref_missing` | `deprecated_removal` |
| `@bc-change BlockMoved` with the new `parent` | `block_moved_parent` | `announced_change` |
| `@bc-change ScopeVariableRemoval` with the `variable` | `block_scope_missing` | `announced_change` |
| `@bc-change ParameterRemoval` with the `parameter`, on the method | `param_missing` | `announced_change` |
| `@bc-change NewRequiredParameter` with the `parameter`, on the method | `param_now_required` | `announced_change` |
| `@bc-change PropBecomesRequired`, on the prop | `prop_now_required` | `announced_change` |
| `@bc-change PropDefaultValueChange` with the new `default` without spaces, `undefined` for a removed one, on the prop | `prop_default_changed` | `announced_change` |
| `@bc-change ParameterDefaultValueChange` with the `parameter` and the new `default`, on the method | `param_default_changed` | `announced_change` |
| `@bc-change BecomesPrivate`, on the member | `visibility_became_private` | `announced_change` |
| `@bc-change ReturnTypeNarrowing` or `ReturnTypeWidening` with the new `type` without spaces, on the method or computed property | `return_type_changed` | `announced_change` |

The marker counts from the version it names on.
A change that happens in the major state only counts as announced when the marker names that major, also when the marker comes with the same pull request.
Any other change counts as announced only when the base commit has the marker already.
The command takes the version the base branch releases next from the major flag: `v6.8.0.0` gives `6.7`.

An extend child inherits the blocks of its parent, so a change to the parent shows up in the child too.
It is reported once, on the parent, and the finding lists the children under `Also affects`.
The services of a class are reported the same way, see "Rules for services".
A public child of a private parent keeps its own finding, because the change is a break for the child only.
For the same reason a private service keeps its own finding apart from the one of its public class.
A component that becomes `@private` with the change was public API until then, so its findings keep their group.

An accepted break, which a file in the repository would allow, does not exist yet.

### Summary

`classifyChanges` in `break-check/break-check.ts` returns the findings as data, and `printFindings` in `break-check/print-findings.ts` prints them as the summary of the break check.
A finding has a group, a rule such as `block_moved_parent`, the id of its element, the flag states it applies to, an explanation, and optionally a fix, a possible rename, the extend children or services it also affects and the location in the head commit.

```text
Public API check failed: 1 unannounced break.

Break (1)
  block_moved_parent  component:sw-example-card/block:sw_example_card_title  current, major
    The block moved from sw_example_card_header to sw_example_card, so ...
    Fix: pattern "Block move". Keep the block under sw_example_card_header while v6.8.0.0 is off, ...
    At: src/app/component/base/sw-example-card/sw-example-card.html.twig:4
```

- The first line is the verdict: `Public API check failed: <n> unannounced break(s).` or `Public API check passed: no breaks.`
- The groups follow in the order `Break`, `Addition`, `Allowed`, `Private`, `Info`, each with its count.
  Empty groups are left out.
- A finding line is `<rule>  <element id>  <states>`.
  Below it, indented four spaces, follow the explanation, `Fix:`, `Possible rename:`, `Also affects:` and `At:`, each only when the finding has it.

The element id names the owner first and the element below it, for example `component:sw-example-card/block:sw_example_card_list_item/scope:item`.
A crossing is named `crossing:<tag>@<opening block>><closing block>`, with `v-if` as the tag of a chain.
A finding about a service as a whole has the id of the service alone, `service:acl`.
The owner and the kind of the element below it, `component` and `block` in this example, decide which unit kind the finding belongs to.

## Fixtures

Each folder in `scripts/generate-public-api/testing/__fixtures__/` is one case: a small source tree before and after a change, and the output expected for it.

| Path | Content |
|---|---|
| `case.json` | A description of the case, the version the base branch releases next, and the major flags |
| `before/`, `after/` | Minimal Administration source tree at the base and at the head commit |
| `expected/before/`, `expected/after/` | The snapshot files for each tree |
| `expected/findings.json` | The findings the break check returns for the change |
| `expected/findings.txt` | How the break check prints these findings |

A source tree holds only what its case is about.
The script part of a component stays empty unless the case covers it, even where the template uses a prop or a method.
This keeps the expected snapshot of a case free of unit kinds that are designed later.

The `expected/` folders are written by hand and are the specification the generator is built against.
`testing/fixtures.spec.ts` checks that `printFindings` prints `findings.json` as `findings.txt`, so the two files cannot drift apart.
The expected snapshots hold whole files with every unit kind, while the generator covers one kind at a time.
`fixtures.spec.ts` therefore compares each case several times:

- Once per unit kind.
  The comparison takes the kind's sections of the `.api.md` reports, its members of the `.d.ts` contracts, its keys in `index.json` and the findings on its elements, and ignores the rest.
- Once as `complete`, which compares every file and all findings.

The blocks have four unit kinds, because they are built in four steps:

| Unit kind | Compares |
|---|---|
| `block-tree` | The block names, their parents, their flag states and their origin in an extend child in the reports, and the block names in the index |
| `block-contract` | The blocks, their context and the crossings in the `.d.ts` contracts |
| `block-structure` | The crossings, and the `v-if` chain and scope markers of the blocks, in the reports |
| `block-findings` | The findings on blocks and crossings |

The other markers of a block line, such as `[after v-if]` and `[scope: item]`, belong to `block-structure`, and the other facets of `Block<>` to `block-contract`.
The comparison moves them out of the block tree into a section `block-context` and a member `blockContext`, which exist only in the comparison.

The members have three unit kinds:

| Unit kind | Compares |
|---|---|
| `component-members` | The sections `inject`, `props`, `emits`, `slots`, `data`, `computed`, `methods` and `refs` in the reports |
| `member-contract` | The members of a component besides its blocks in the `.d.ts` contracts |
| `member-findings` | The findings on props, computed properties, methods and refs |

A service has two unit kinds:

| Unit kind | Compares |
|---|---|
| `service-units` | The header of a service in the reports, its entry in the index, and the findings about the service as a whole |
| `service-members` | The sections `properties` and `methods` of a service in the reports, and the findings on its properties and methods |

A unit kind belongs to components or to services, and it only reads the outlines, index entries and findings of those.
The `methods` section of a service is therefore no part of `component-members`.

A unit kind can only list a part that some case expects.
No case has mixins, provided values or notes, so `read/options/option-members.spec/snapshot.spec.ts` covers those sections.

`testing/expected-failures.ts` lists what cannot pass yet, in two lists:

- `views` names a unit kind, or `complete`, that no case passes yet.
  It marks the cases that expect something of that view.
  A case that expects nothing of it, as a service case does of the blocks, has to pass it already.
- `comparisons` names single comparisons such as `block-moved/block-findings`, for a view that other cases pass already.

The spec runs every listed comparison with `it.failing`, so the run is green while they fail and turns red as soon as one of them passes.
An entry that names no view or comparison is an error, and so is a comparison whose view is listed already.
So is a section, member, key or element kind of a unit kind that occurs in no expected output, because it would compare nothing.

To build the next part:

1. Add its fixture cases, and its unit kind to `UNIT_KINDS` in `testing/fixture-runner.ts` when it is a new one.
   A new unit kind goes into `views` of `testing/expected-failures.ts`.
2. Implement it behind `generateSnapshot` in `snapshot/snapshot.ts` and `classifyChanges` in `break-check/break-check.ts`.
   `read/build-model.ts` reads the sources into the model of `model/model.ts`, and the printers turn the model into files, so a new part adds to the model and to each printer.
3. Once some cases pass the kind, remove it from `views` and list the cases that still fail in `comparisons`.
   The part is done when its entries are gone.
4. Run `composer admin:generate-public-api` and commit the changed files in `public-api/`.

Run the comparisons in `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false scripts/generate-public-api
```
