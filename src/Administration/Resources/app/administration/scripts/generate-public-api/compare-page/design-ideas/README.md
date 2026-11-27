# Design ideas for the compare page

Four directions for the compare page, built with the Meteor Vue components and real findings.
They are a design exploration and do not replace `compare-page/`.
The pages do not call GitHub or run the check.

## The chosen layout

`one-field-split.html` is the layout to continue with.
It has one field for the comparison at the top and two panes below it.

The left pane is a tree of the changed units with its own search.
It groups the units by kind first and by folder second.
A unit is closed at first and shows how many breaks, new elements and info entries it has.
An open unit lists its entries, each tinted in the colour of its group.

The right pane starts with a summary of the comparison.
A picked break says what changed in one sentence and has three tabs: the changed code, plugin code that breaks, and the fix with the line where its comment goes.

`screenshots/` shows the layout with the small and the large report.

Everything in the detail comes from the break check and the sources of the two commits.
The headline, the plugin code and the comment of the fix are the `detail` of a finding, see `explainChanges` in `break-check/break-check.ts`.
`data/dump-findings.ts` cuts the changed code and the code of the fix out of both commits with `compare-page/finding-code.ts` and writes them into the data.
An entry of several places, such as one variable that is gone in five blocks, shows the code of its first place and lists all places with the fix.

## View them

Build in the Administration folder, inside the container:

```bash
node_modules/.bin/ts-node --transpileOnly --cwd-mode scripts/generate-public-api/compare-page/design-ideas/build.ts
```

Then open `design-ideas/dist/index.html` in a browser.
It works as a file, without a server.
The overview links each direction, and a bar at the bottom of each page switches between its states.

## Data

`data/small.js` holds the findings of pull request #20959, `data/large.js` those of v6.7.0.0 against trunk.
Regenerate them with:

```bash
node_modules/.bin/ts-node --transpileOnly --cwd-mode scripts/generate-public-api/compare-page/design-ideas/data/dump-findings.ts small 9a0fd3338437^ 9a0fd3338437
node_modules/.bin/ts-node --transpileOnly --cwd-mode scripts/generate-public-api/compare-page/design-ideas/data/dump-findings.ts large v6.7.0.0 origin/trunk
```

## Files

| File                                 | What it does                                                                                          |
| ------------------------------------ | ----------------------------------------------------------------------------------------------------- |
| `src/design-overview.vue`            | The overview: idea, omissions and weaknesses of each direction, and a comparison table                |
| `src/directions/direction-*.vue`     | One file per direction                                                                                |
| `src/one-field-layouts.ts`           | The layouts of direction "One field" that have a page of their own                                    |
| `src/directions/one-field-split.vue` | The chosen layout: tree with search on the left, summary or detail on the right                       |
| `src/directions/split-*.vue`         | The parts of the chosen layout: a folder and a row of the tree, the summary, the detail with its tabs |
| `src/shared/clusters.ts`             | Groups the findings by unit and merges the findings that share a rule and a fix                       |
| `src/shared/split-tree.ts`           | Builds the tree of kinds, folders and units with their counts, and holds the words of the groups      |
| `src/shared/base.css`                | The reset, and the tint, bar and text colour of each group                                            |
| `src/shared/code-diff.vue`           | A block of code with removed, added and left out lines, and a line it can point out                   |
| `src/shared/states.ts`               | The states every direction shows, picked by the address hash, and the played loading progress         |
| `src/shared/report.ts`               | Helpers that slice the findings: unit, member, fix text, source link                                  |
| `src/shared/design-shell.vue`        | The review bar around a direction                                                                     |
| `build.ts`                           | Builds one classic script with Vue and Meteor, so the pages open as files                             |
