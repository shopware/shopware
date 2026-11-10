# Public API snapshot

> Status: In-Progress

The Administration gets a generated snapshot of its public API and a check that blocks unannounced breaks, see [#22591](https://github.com/shopware/shopware/issues/22591).
Both are built in steps.
Two parts exist so far: the markers that announce a change, and the fixtures that the generator and the break check have to satisfy.
The grammar of the markers is in `eslint-rules/deprecation-rules/utils/api-markers.ts`, the code of the generator in `scripts/generate-public-api/`.

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

Only the format is checked so far.
Whether a marker sits on an element its kind fits, and whether its version is not released yet, comes with the break check.

## Fixtures

Each folder in `scripts/generate-public-api/__fixtures__/` is one case: a small source tree before and after a change, and the output expected for it.

| Path | Content |
|---|---|
| `case.json` | What the case covers, the version the base branch releases next, and the major flags |
| `before/`, `after/` | Minimal Administration source tree at the base and at the head commit |
| `expected/before/`, `expected/after/` | The snapshot files for each tree |
| `expected/findings.txt` | The summary the break check prints for the change |

The `expected/` folders are written by hand and are the specification the generator is built against.
They hold whole files with every unit kind, while the generator covers one kind at a time.
`fixtures.spec.ts` therefore compares each case several times:

- Once per unit kind.
  The comparison takes the kind's sections of the `.api.md` reports, its members of the `.d.ts` contracts, its keys in `index.json` and the findings on its elements, and ignores the rest.
  `blocks` is the only kind so far, and it covers the block tree and the crossings.
- Once as `complete`, which compares every file and the whole summary.

`expected-failures.ts` lists the comparisons that cannot pass yet, by name, for example `block-moved/blocks`.
The spec runs them with `it.failing`, so the run is green while they fail and turns red as soon as one of them passes.
An entry that names no comparison is an error.
So is a unit kind that occurs in no expected snapshot, because it would compare nothing.

To build the next part:

1. Add its fixture cases, and its unit kind to `UNIT_KINDS` in `fixture-runner.ts` when it is a new one.
2. Implement it behind `generateSnapshot` in `snapshot.ts` and `classifyChanges` in `break-check.ts`.
3. Remove the entries that now pass from `expected-failures.ts`.
   The part is done when its entries are gone.

Run the comparisons in `src/Administration/Resources/app/administration`:

```bash
npx jest --collectCoverage=false scripts/generate-public-api
```
