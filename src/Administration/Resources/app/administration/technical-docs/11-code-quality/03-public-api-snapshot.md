# Public API snapshot

> Status: In-Progress

The Administration gets a generated snapshot of its public API and a check that blocks unannounced breaks, see [#22591](https://github.com/shopware/shopware/issues/22591).
Both are built in steps.
Four parts exist so far: the snapshot of the components with the blocks of their templates, the markers that announce a change, the summary the break check prints, and the fixtures that the generator and the break check have to satisfy.
The grammar of the markers is in `eslint-rules/deprecation-rules/utils/api-markers.ts`, the code of the generator in `scripts/generate-public-api/`.

## Snapshot

The snapshot is committed in `public-api/`.
It lists every component that core registers, with the blocks of its template and their nesting.
A pull request that adds, renames, moves or removes a block therefore shows that change in its diff.

Regenerate it after such a change and commit the result:

```bash
composer admin:generate-public-api
```

`scripts/generate-public-api/committed-snapshot.spec.ts` fails while the committed files differ from the sources.
When a block of the committed snapshot is gone from the templates, it reports that as a breaking change and names the block.
Regenerating the snapshot makes both tests pass, because they compare files and judge no change.
The break check that blocks an unannounced break is not built yet.

### Files

A component has one report, named after the component and placed beside its folder, without the leading `src/`.
`src/module/sw-order/page/sw-order-detail/index.js` gives `public-api/module/sw-order/page/sw-order-detail.api.md`.

| File | Content |
|---|---|
| `<name>.api.md` | The report for reviewers, which merges both flag states |
| `index.json` | The components with their file, visibility, parent and block names, for tools such as ESLint rules |

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

- An extend child lists the blocks of its own template.
  The blocks it inherits from its parent follow with the extend chains.
- A core `Component.override` adds no entry.
- The other parts of a component, such as props, methods and slots, are missing.
- The `.d.ts` contracts are not generated yet.
  They arrive together with the entry points `current.d.ts` and `major.d.ts`, so they can be type-checked from the start.
  Until then, the fixture view `block-contract` is on the expected-fail list.

The generator stops with an error in four cases: a block in a template that no registered component uses, a block tag in a place the HTML parser cannot see, a registration whose component name or import it cannot read, and a component name that is registered twice.

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
The list of kinds can still change until the break check uses them.

`eslint-rules/deprecation-rules/utils/api-markers.ts` holds the grammar and the kinds.
The ESLint rule `sw-deprecation-rules/valid-api-markers` uses it and reports, in `.js`, `.ts`, `.vue` and `.html.twig` files:

- a typo in the tag name one edit away, such as `@bc-chnage` or `@deprecate`
- a missing or malformed version
- an unknown kind
- a missing key, or a key the kind does not take

Without the rule such a comment would stay a plain comment, and the change would surface as an unannounced break in the major.

The PHP test `AnnotationTagTester` checks the version of `@deprecated` in every file of the repository as well, with its own grammar.
A change to the `@deprecated` grammar has to be made in both places.

Only the format is checked so far.
Whether a marker sits on an element its kind fits, and whether its version is not released yet, comes with the break check.

## Findings

`classifyChanges` in `break-check.ts` returns the findings as data, and `formatFindings` in `findings.ts` prints them as the summary of the break check.
A finding has a group, a rule such as `block_moved_parent`, the id of its element, the flag states it applies to, an explanation, and optionally a fix, a possible rename and the location in the head commit.

```text
Public API check failed: 1 unannounced break.

Break (1)
  block_moved_parent  component:sw-example-card/block:sw_example_card_title  current, major
    The block moved from sw_example_card_header to sw_example_card, so ...
    Fix: pattern "Block move". Keep the block under sw_example_card_header while v6.8.0.0 is off, ...
    At: src/app/component/base/sw-example-card/sw-example-card.html.twig:4
```

- The first line is the verdict: `Public API check failed: <n> unannounced break(s).` or `Public API check passed: no breaks.`
- The groups follow in the order `Break`, `Addition`, `Info`, each with its count.
  Empty groups are left out.
- A finding line is `<rule>  <element id>  <states>`.
  Below it, indented four spaces, follow the explanation, `Fix:`, `Possible rename:` and `At:`, each only when the finding has it.

The element id names the owner first and the element below it, for example `component:sw-example-card/block:sw_example_card_list_item/scope:item`.
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
| `block-tree` | The block names, their parents and their flag states in the reports and the index |
| `block-contract` | The same in the `blocks` member of the `.d.ts` contracts |
| `block-structure` | The crossings, and the `v-if` chain and scope markers of the blocks |
| `block-findings` | The findings on blocks and crossings |

The markers of a block line other than its flag state, such as `[after v-if]` and `[scope: item]`, and the type arguments of `Block<>` after the parent belong to `block-structure`.
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
