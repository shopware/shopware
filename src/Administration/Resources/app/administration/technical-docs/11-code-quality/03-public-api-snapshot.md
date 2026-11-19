# Public API snapshot

> Status: In-Progress

The Administration gets a generated snapshot of its public API and a check that blocks unannounced breaks, see [#22591](https://github.com/shopware/shopware/issues/22591).
Both are built in steps.
Four parts exist so far: the snapshot of the components with the blocks they render, the markers that announce a change, the break check for blocks, and the fixtures that the generator and the break check have to satisfy.
The grammar of the markers is in `eslint-rules/deprecation-rules/utils/api-markers.ts`, the code of the generator in `scripts/generate-public-api/`.

## Snapshot

The snapshot is committed in `public-api/`.
It lists every component that core registers, with the blocks of the template it renders and their nesting.
A pull request that adds, renames, moves or removes a block therefore shows that change in its diff.

Regenerate it after such a change and commit the result:

```bash
composer admin:generate-public-api
```

`scripts/generate-public-api/committed-snapshot.spec.ts` fails while the committed files differ from the sources.
When a block of the committed snapshot is gone from the templates, it reports that as a breaking change and names the block.
Regenerating the snapshot makes both tests pass, because they compare files and judge no change.
The break check judges a change, see "Break check".
It runs on demand so far and blocks nothing.

### Files

A component has one report, named after the component and placed beside its folder, without the leading `src/`.
`src/module/sw-order/page/sw-order-detail/index.js` gives `public-api/module/sw-order/page/sw-order-detail.api.md`.

| File | Content |
|---|---|
| `<name>.api.md` | The report for reviewers, which merges both flag states |
| `index.json` | The components with their file, visibility, parent and the names of the blocks they render, for tools such as ESLint rules |

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
Such a block is in no report, and `scripts/generate-public-api/extend-chain.spec/runtime.spec.ts` fails when a template declares one.
The same spec registers every template with the runtime factory and compares the blocks of each component with the snapshot.

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
`scripts/generate-public-api/block-structure.spec/compiler.spec.ts` asks the Vue compiler the same questions about every block of the Administration and compares the answers.

The generator also keeps the conditions of the chain a block follows, whether the content of the block continues that chain, and where each variable comes from, for example `v-for products` or `slot sw-data-grid#actions`.
The break check compares them, see "Break check".
The files do not contain them, so an edit to a condition changes no file.

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

### Visibility

Everything without `@private` is public.
A component is private when the comment directly above its `export default`, or directly above its registration, has an `@private` tag.
A private component stays in the snapshot, and its report marks every line `[private]`.

### Not covered yet

- A core `Component.override` adds no entry of its own, and the report does not say which blocks come from one.
- A component that keeps its blocks in a `.vue` file cannot be extended, and cannot be overridden with a Twig template.
- The other parts of a component, such as props, methods and slots, are missing.
- The report does not show the `@deprecated` and `@bc-change` markers of a block, although the break check reads them.
- The `.d.ts` contracts are not generated yet.
  They arrive together with the entry points `current.d.ts` and `major.d.ts`, so they can be type-checked from the start.
  Until then, the fixture view `block-contract` is on the expected-fail list.

The generator stops with an error when it cannot read something:

- a block in a template that no registered component or core override uses
- a block or `{% parent %}` tag in a place the HTML parser cannot see
- a registration or override whose component name, import or index it cannot read
- a component name that is registered twice
- an extend chain that names an unregistered component, loops, or has a child with blocks below a parent without a Twig template
- a core override of an unregistered component, or one that is registered in one flag state only

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
| `EmitPayloadChange` | The payload of an event changes | none |
| `RouteParentChange` | A route moves under another parent route, or to the top level | `parent` |
| `RegistryGroupChange` | A registry entry, for example a rule condition, moves to another group | `group` |

The first five kinds have the names of the PHP attributes in `src/Core/Framework/Deprecation/BCChange/`.
Their keys are shorter than the PHP arguments, for example `parameter` for `parameterName` and `type` for `newType`.
`BecomesPrivate` is what PHP calls `BecomesInternal`.
The other kinds exist only in the Administration.
The break check uses `BlockMoved` and `ScopeVariableRemoval` so far.
The other kinds can still change until it uses them.

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
With `--history <range> <result.json>` it checks every commit of the range that changed the Administration sources against its first parent, and writes the findings per commit.
That run measures the check itself: how many pull requests it would have stopped, and for which reasons.
An older commit can hold templates that no component uses.
The generator rejects those for the committed snapshot, and the check leaves them out, because it prints no snapshot.

### Rules for blocks

`block-rules.ts` holds one rule per kind of change.
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
  A component that comes or goes is not reported yet.

### Groups

The rule of a finding names the change, and its group says what the change counts as.

| Group | Meaning |
|---|---|
| `Break` | An unannounced break. Only this group fails the check |
| `Addition` | New public API |
| `Allowed` | A change that a marker on the block announced |
| `Private` | A change to a component that is `@private` in one of the commits |
| `Info` | A change that a reviewer may want to look at |

A marker announces a change when it sits in the Twig comments directly before the `{% block %}` tag:

| Marker | Announces | Rule of the finding |
|---|---|---|
| `@deprecated tag:vX.Y.Z` | `block_missing` | `deprecated_removal` |
| `@bc-change BlockMoved` with the new `parent` | `block_moved_parent` | `announced_change` |
| `@bc-change ScopeVariableRemoval` with the `variable` | `block_scope_missing` | `announced_change` |

The marker counts from the version it names on.
A change that happens in the major state only counts as announced when the marker names that major, also when the marker comes with the same pull request.
The command takes the version the base branch releases next from the major flag: `v6.8.0.0` gives `6.7`.

An extend child inherits the blocks of its parent, so a change to the parent shows up in the child too.
It is reported once, on the parent, and the finding lists the children under `Also affects`.
A public child of a private parent keeps its own finding, because the change is a break for the child only.

An accepted break, which a file in the repository would allow, does not exist yet.

### Summary

`classifyChanges` in `break-check.ts` returns the findings as data, and `formatFindings` in `findings.ts` prints them as the summary of the break check.
A finding has a group, a rule such as `block_moved_parent`, the id of its element, the flag states it applies to, an explanation, and optionally a fix, a possible rename, the extend children it also affects and the location in the head commit.

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
The kind of the element below the owner, `block` in this example, decides which unit kind the finding belongs to.

## Fixtures

Each folder in `scripts/generate-public-api/__fixtures__/` is one case: a small source tree before and after a change, and the output expected for it.

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
`fixtures.spec.ts` checks that `formatFindings` prints `findings.json` as `findings.txt`, so the two files cannot drift apart.
The expected snapshots hold whole files with every unit kind, while the generator covers one kind at a time.
`fixtures.spec.ts` therefore compares each case several times:

- Once per unit kind.
  The comparison takes the kind's sections of the `.api.md` reports, its members of the `.d.ts` contracts, its keys in `index.json` and the findings on its elements, and ignores the rest.
- Once as `complete`, which compares every file and all findings.

The blocks have four unit kinds, because they are built in four steps:

| Unit kind | Compares |
|---|---|
| `block-tree` | The block names, their parents, their flag states and their origin in an extend child, in the reports and the index |
| `block-contract` | The blocks, their context and the crossings in the `.d.ts` contracts |
| `block-structure` | The crossings, and the `v-if` chain and scope markers of the blocks, in the reports |
| `block-findings` | The findings on blocks and crossings |

The other markers of a block line, such as `[after v-if]` and `[scope: item]`, belong to `block-structure`, and the other facets of `Block<>` to `block-contract`.
The comparison moves them out of the block tree into a section `block-context` and a member `blockContext`, which exist only in the comparison.

`expected-failures.ts` lists what cannot pass yet, in two lists:

- `views` names a unit kind, or `complete`, that no case passes yet.
- `comparisons` names single comparisons such as `block-moved/block-findings`, for a view that other cases pass already.

The spec runs every listed comparison with `it.failing`, so the run is green while they fail and turns red as soon as one of them passes.
An entry that names no view or comparison is an error, and so is a comparison whose view is listed already.
So is a section, member, key or element kind of a unit kind that occurs in no expected output, because it would compare nothing.

To build the next part:

1. Add its fixture cases, and its unit kind to `UNIT_KINDS` in `fixture-runner.ts` when it is a new one.
   A new unit kind goes into `views` of `expected-failures.ts`.
2. Implement it behind `generateSnapshot` in `snapshot.ts` and `classifyChanges` in `break-check.ts`.
   The generator reads the sources into the model of `model.ts`, and the printers turn the model into files, so a new part adds to the model and to each printer.
3. Once some cases pass the kind, remove it from `views` and list the cases that still fail in `comparisons`.
   The part is done when its entries are gone.
4. Run `composer admin:generate-public-api` and commit the changed files in `public-api/`.

Run the comparisons in `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false scripts/generate-public-api
```
