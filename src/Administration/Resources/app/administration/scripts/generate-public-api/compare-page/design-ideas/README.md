# Design ideas for the compare page

Four layouts for the compare page, built with the Meteor Vue components and real findings.
They are a design exploration and do not replace `compare-page/`.
The pages do not call GitHub or run the check.

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

| File | What it does |
|---|---|
| `src/design-overview.vue` | The overview: idea, omissions and weaknesses of each direction, and a comparison table |
| `src/directions/direction-*.vue` | One file per direction |
| `src/shared/states.ts` | The states every direction shows, picked by the address hash, and the played loading progress |
| `src/shared/report.ts` | Helpers that slice the findings: unit, member, fix text, source link |
| `src/shared/design-shell.vue` | The review bar around a direction |
| `build.ts` | Builds one classic script with Vue and Meteor, so the pages open as files |
