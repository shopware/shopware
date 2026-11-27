# Public API snapshot generator

This folder generates the snapshot of the Administration public API in `public-api/`.
It also holds the break check, which compares the snapshots of two commits, and the fixtures for both.

The format of the generated files, the markers, and the steps to add a new part are described in `technical-docs/11-code-quality/03-public-api-snapshot.md`.
This file explains how the code is organised.
Each file says in its header, or above the one thing it exports, what it does.

## Folders

A run goes through the folders in this order.
A source file only imports from its own folder and from the folders above it in the table.
A spec also imports from `testing/` and from the step it observes the result through, mostly `snapshot/`.

| Folder | What it holds |
|---|---|
| `helpers/` | Small pure functions that every step may use, such as the code point order. It imports from no other folder |
| `source/` | Lists and reads the source files, from a checkout or from a commit in git, and knows where this checkout is |
| `model/` | The public API as data: the types every later step works on, without a parser and without file access |
| `read/` | Turns the sources into the model, with `template/` for Twig and Vue templates, `options/` for the options object of a component, `services/` for the registered services, `stores/` for the registered stores and `globals/` for the `Shopware` object |
| `snapshot/` | Prints the model into the files of `public-api/` |
| `break-check/` | Compares two models, with `facts/` for what the rules compare about a unit and `rules/` for the rules |
| `compare-page/` | A static page that runs the break check in the browser, on two commits it loads from GitHub. See "Compare page" |
| `testing/` | The fixture cases, their runner, and the example components, services, stores and `Shopware` objects the specs share |

`generate.ts` and `check.ts` are the two commands, and `compare-page/build.ts` builds the page.
`snapshot/` and `break-check/` take one thing from `read/`: `buildModel` with its options, which they call to get a model.

## How a run works

```mermaid
flowchart TD
    subgraph source["source/"]
        tree["source-tree.ts<br/>lists and reads the source files"]
    end
    subgraph read["read/"]
        registry["component-registry.ts<br/>finds the registered components and the core overrides"]
        blocks["template/template-blocks.ts<br/>reads the blocks of a template as a tree"]
        chain["template/extend-chain.ts<br/>merges the trees along the extend chains and applies the overrides"]
        rendered["template/rendered-template.ts<br/>the merged template of a component as HTML, parsed once"]
        structure["template/block-structure.ts<br/>reads the v-if chains, variables and crossings of a merged template"]
        templateMembers["template/template-members.ts<br/>reads the slots and refs of a merged template"]
        options["options/component-options.ts, options/option-members.ts<br/>find the options object and read the members it declares"]
        flags["flag-conditions.ts<br/>current state, major state or both"]
        services["services/, stores/, globals/<br/>find the registered services and stores and the objects of the Shopware object, and read what is behind each one"]
        build["build-model.ts<br/>puts one unit per component together and adds the other units"]
    end
    subgraph model["model/"]
        data["model.ts, blocks.ts, members.ts, flag-states.ts, source-line.ts<br/>the public API as data"]
    end
    subgraph snapshot["snapshot/"]
        printers["print-report.ts, print-members.ts, print-index.ts<br/>print the files"]
        files["snapshot.ts<br/>collects all printed files"]
        committed["committed-snapshot.ts<br/>writes them to public-api/"]
    end

    tree --> registry --> chain
    tree --> blocks --> chain --> rendered --> structure --> build
    rendered --> templateMembers --> build
    registry --> options --> build
    tree --> services --> build
    flags -.-> registry
    flags -.-> blocks
    build --> data --> printers --> files --> committed
```

`generateSnapshot` in `snapshot/snapshot.ts` returns the files in memory, so the tests run without touching the disk.
`snapshot/committed-snapshot.ts` is the only file that writes to `public-api/`.

Every element in the model carries a `FlagStates` value that says in which of the two flag states it exists.
`read/build-model.ts` limits the elements of a component to the states in which the component is registered, so the printers only split or merge states.
A new kind of element goes through `existingIn` in `componentUnit`.

`read/template/template-blocks.ts` replaces every `{% block %}`, `{% endblock %}` and `{% parent %}` with an HTML comment and parses the result with `@vue/compiler-dom`.
That way Twig blocks and `<sw-block>` elements end up in one tree, and the `v-if` chains around a block decide its flag state.
`read/template/template-nodes.ts` holds the parser call and the node helpers that the template readers share.
`read/template/flag-branches.ts` follows the `v-if` chains of a parsed template for `template-members.ts`.
The tree keeps the order of the source, because the merge in `extend-chain.ts` depends on it.
The tree also keeps the HTML between the Twig tags as markup, which the merge carries along without reading it.
`listBlocks` in `read/template/block-tree.ts` turns a merged tree into the entries of `model/blocks.ts`, which the printers use.

`read/template/extend-chain.ts` follows the rules of `src/core/factory/template.factory.js` and names the function of the runtime above each of its own.
A block keeps two flag state values: `within`, the conditions between it and the block around it, and `states`, where it renders.
When a redeclaration moves content to another place, `states` is computed again from `within`, because the conditions around the old place no longer apply.

`read/template/rendered-template.ts` puts the merged tree of a component together as one template again and parses it once more.
What an override can rely on depends on the template Vue compiles, and a block of an extend child sits between markup of its parent.
`block-structure.ts` and `template-members.ts` both read that template, so neither needs rules of its own for `{% parent %}` or for a block that moved.

`model/members.ts` describes a member of a component: a prop, a method, a slot, a ref and so on.
Every kind is a list of named members with a few details of its own, so the printer and the rules of the break check handle all kinds the same way.
A new kind needs its details and its entry in `MEMBER_KINDS`, a reader, a line format in `snapshot/print-members.ts` and an entry in `COMING_AND_GOING` in `break-check/rules/member-rules.ts`.
The compiler reports each of these while it is missing, and also a kind with a type that `TYPED` in `member-rules.ts` does not list.
A kind that plugin code calls has to join `CALLABLE_KINDS` in `model/members.ts` by hand, which gives it the rules for parameters and results.
`read/options/option-members.ts` reads the kinds of the options object from the syntax tree, with one reader per option in `SECTIONS`, and never runs the source.
`read/options/option-helpers.ts` lists the helpers that components spread into `computed` and `methods`, with the names each call adds.
What the reader cannot resolve becomes a note in the report, because a member that silently goes missing would look like a removed API.
`read/script-nodes.ts` holds the node helpers for scripts, as `template-nodes.ts` does for templates.

`read/declarations.ts` finds what a name in a script stands for: a `const`, a function or a class of an enclosing scope or of the file, or what an import brings in.
`read/services/service-registry.ts` finds the registrations with it, and `provided-object.ts` follows a provider to what it returns.
`read/resolved-object.ts` says what such a value is as an object: an instance of a class, an object literal, a class or a function.
`read/object-members.ts` reads the members of a class or a literal, and `service-units.ts` puts the units together, with the classes that services are instances of.
The readers of the stores and of the `Shopware` object use `resolved-object.ts` and `object-members.ts` too, so both stand beside the folders of the unit kinds.
`read/member-reading.ts` holds what the readers of members share: the name, line and comment of a member, the signature of a function, the walk over the members of an object literal, and what a value is to code that reads it.
A service has the member kinds `property` and `method`, so the printers and the rules for a method are those of a component.
`read/stores/store-registry.ts` finds the `Store.register` calls, `store-members.ts` reads the state, getters and actions of an options store and of a setup store, and `store-units.ts` puts the units together.
An action is a callable kind like a method, see `CALLABLE_KINDS` in `model/members.ts`, so the rules for a method run on it.
`read/registered-units.ts` builds what a service and a store share: the files, the visibility and the markers of a unit that a script declares.
`unitOf` builds a unit from its parts, which the `Shopware` object uses too, and `objectDoc` reads the comments about an object for a service and for the `Shopware` object alike.
`read/global-interfaces.ts` reads `ServiceContainer` and `PiniaRootState`.
`read/globals/global-units.ts` builds the units of the `Shopware` object: it starts at `ShopwareClass` and follows every member that holds an object, two objects down.
`read/declarations.ts` follows `object.member`, a namespace import, a destructuring and the global name `Shopware` for it, and `resolved-object.ts` says what a getter returns and what a member of an object is.
`read/vue-values.ts` names the calls of Vue that build state or a getter, for the readers of stores and of objects.
A member that names a declaration of another file gets the comments of that declaration from `namedValue` in `read/member-reading.ts`.
`declarationDocs` in `read/script-nodes.ts` decides which comment speaks for a value.

`snapshot/report-outline.ts` holds the lines and sections a report is made of, and the file around them.
`print-report.ts` fills it for a component, `print-registered-reports.ts` for the services and for the stores of a source file, and `print-members.ts` prints the member sections of all of them.

## How the break check works

```mermaid
flowchart TD
    git["source/git-source-tree.ts<br/>reads the sources of a commit out of git"]
    subgraph check["break-check/"]
        commits["commit-check.ts<br/>builds the models of two commits"]
        units["units.ts<br/>the components, services, stores and Shopware objects of a model as units, and what the check knows per kind"]
        facts["facts/<br/>what the rules compare about a unit in one flag state"]
        rules["rules/<br/>what a rule is made of, the rules per kind of change, and the list of all rules"]
        classify["break-check.ts<br/>runs the rules per unit and state, then decides the group"]
        whole["unit-changes.ts<br/>reports a unit that is gone, new or private"]
        announced["announcements.ts<br/>decides whether a marker announced a change"]
        use["plugin-use.ts<br/>how plugin code uses a unit of each kind, for the texts"]
        print["print-findings.ts<br/>prints the summary"]
    end

    git --> commits --> units --> classify
    facts --> rules --> classify --> print
    units --> whole --> classify
    announced --> classify
    announced --> whole
    use --> classify
```

`classifyChanges` in `break-check/break-check.ts` takes the models of two commits and returns findings, without touching git or the disk.
A rule knows nothing about flag states, markers or visibility.
It gets the facts of one unit in one state at the base and at the head, and returns the changes with the element each one is about.
`units.ts` turns the components, the services and the stores of a model into units, so the rules run on all of them.
It also makes a unit of each class that services are instances of, which only reports a change that several of its services share.
A service or a store has no template, so its facts hold no blocks and the block rules find nothing.
`break-check.ts` joins what a rule found in both states, and then decides whether the unit is private and whether an extend child or a service only inherits the finding.
`announcements.ts` decides whether a marker announced the change.
The text of a finding gets the wording for its kind of unit from `plugin-use.ts`: a plugin reaches a method of a component through `$super`, one of a service through `Shopware.Service('<name>')`, and an action of a store through `Shopware.Store.get('<id>')`.
`unit-changes.ts` compares the units themselves and reports a service or a store that only one commit has, or that became private.
An object of the `Shopware` object comes and goes with the member that holds it, so the member rules report it there.
`unit-changes.ts` reports it only when both commits have that member and the snapshot can read the object behind it in one of them only.
`rules/texts.ts` holds the texts that such a finding shares with the one about a member, and `findingFor` in `finding.ts` puts both kinds of finding together.

`explainChanges` returns the same findings with a `detail` each, for a view that shows one finding at a time.
The detail holds a headline that names the element, the line of the element at the base commit, the comment that the fix adds, and plugin code that the change breaks.
A rule writes the headline and the comment beside its other texts, and a rule of the group `Break` does not compile without a headline.
The plugin code belongs to the element of a change and comes from `plugin-use.ts`: a template override for a block, and for a member the way plugin code of its kind of unit uses it.
A rule only sets its own when that code would not break, as an override of a moved block still works.
`rules/texts.ts` builds the tags of the markers once, for the text of the fix and for the comment.
`classifyChanges` leaves the detail out, so the summary and the fixtures do not change with it.
A new rule for blocks is one entry in `BLOCK_RULES`, and one for members an entry in `MEMBER_RULES`.
A new kind of unit is one entry in `UNIT_KINDS` in `units.ts`, with how the model lists its units and how plugin code uses one.
`rules/member-rules.ts` builds the rules for a member that is gone or new from `COMING_AND_GOING`, which has one entry per kind, so a new kind gets them without a rule of its own.
The rules for another part of a component get a file of their own in `rules/` and join `RULES` in `rules/index.ts`.
Its facts get a file of their own in `facts/` and join `UnitFacts` in `facts/unit-facts.ts`.

## Compare page

The break check is a function of two source trees, so it also runs in a browser.
`compare-page/` is a static page that takes a pull request, or two branches, tags or commits, and shows the findings with a filter.
It loads the file lists of both commits from the GitHub REST API and the files from raw.githubusercontent.com, without a login.
That also works for the commits of a fork.

Build it in the Administration folder:

```bash
npm run build-public-api-page
```

The result is `compare-page/dist/`, with `index.html` and one script.
It can be opened as a file or served from any static host.
`?pr=23448` or `?base=v6.7.0.0&head=trunk` in the address starts a comparison.

| File | What it does |
|---|---|
| `remote-source-tree.ts` | Loads the source trees of commits from a remote, as `source/git-source-tree.ts` reads them from git. A file that two commits share is loaded once |
| `github.ts` | The remote: resolves a pull request to its head and the commit it branched off, lists the files of a commit and reads them |
| `content-cache.ts` | Keeps loaded files in the Cache Storage of the browser by their git hash, so the next comparison only loads what changed |
| `report-view.ts` | Renders the findings: the verdict, a switch per group, a text filter, and the summary as the command prints it |
| `finding-code.ts` | Cuts the code for one finding out of both source trees: the lines that changed, and the line where the comment of the fix goes. Only the design ideas use it so far |
| `page.ts`, `main.ts`, `index.html` | The form, the progress and the wiring: `page.ts` loads both trees and hands them to `compareSourceTrees` of the break check. `main.ts` is the entry of the bundle |
| `build.ts`, `browser-stubs/` | Builds the bundle with Vite. The stubs stand in for `node:fs` and `node:path`, which the generator modules import for reading a checkout |

The bundle holds the generator and the major flags of the checkout it was built from, as the command uses those of its checkout.
The page is a prototype: it reports what the command reports for the same two commits, but the check in the pipeline decides.
GitHub allows 60 API requests per hour and address without a login, and a comparison takes two to four.

## Rules the code follows

- The generator throws when it cannot read something, for example a block that belongs to no registered component.
  A silent gap in the snapshot would look like a removed API.
- The same sources always give the same bytes.
  Everything is sorted with `byCodePoint`, and the files contain no absolute paths, line numbers or dates.
- The major flags are an argument of `generateSnapshot`.
  The feature flags that are active in the process have no effect on the result.

## Tests

| Spec | What it checks |
|---|---|
| `testing/fixtures.spec.ts` | Every case in `testing/__fixtures__/` against its hand-written expected output |
| `snapshot/snapshot.spec.ts` | Single behaviours of the generator on small source trees given as strings |
| `read/template/extend-chain.spec/snapshot.spec.ts` | How the snapshot shows extend children and core overrides, and the chains the generator rejects |
| `read/template/extend-chain.spec/runtime.spec.ts` | The merged blocks against the runtime: one case per merge rule, and every component of the real Administration |
| `read/template/block-structure.spec/snapshot.spec.ts` | How the snapshot shows the context of a block and the crossings, also for extend children |
| `read/template/block-structure.spec/compiler.spec.ts` | The context and the crossings against the Vue compiler, for every block of the real Administration |
| `read/options/option-members.spec/snapshot.spec.ts` | How the snapshot shows each kind of member, in every form the sources write one |
| `read/options/option-members.spec/runtime.spec.ts` | The member names against the options objects at runtime, for every component of the real Administration |
| `read/services/service-units.spec.ts` | How the snapshot shows a service, for every way the sources register and declare one, and the registrations the generator rejects |
| `read/stores/store-units.spec.ts` | How the snapshot shows an options store and a setup store, and the registrations the generator rejects |
| `read/globals/global-units.spec.ts` | How the snapshot shows the `Shopware` object and the objects below it, with their visibility |
| `break-check/break-check.spec/block-rules.spec.ts` | What each rule of the break check reports for a change to the blocks |
| `break-check/break-check.spec/member-rules.spec.ts` | What each rule of the break check reports for a change to the members |
| `break-check/break-check.spec/method-rules.spec.ts` | What each rule of the break check reports for a change to the parameters, the result and the return type of a method |
| `break-check/break-check.spec/service-rules.spec.ts` | What the break check reports for a service that is gone, new or private, and for a change to its members |
| `break-check/break-check.spec/global-rules.spec.ts` | What the break check reports for the members of the `Shopware` object, and for an object below it that comes, goes or becomes private |
| `break-check/break-check.spec/store-rules.spec.ts` | What the break check reports for a store that is gone, new or private, and for a change to its state, getters and actions |
| `break-check/break-check.spec/verdict.spec.ts` | What the break check reports as announced, private or inherited by an extend child |
| `break-check/break-check.spec/finding-details.spec.ts` | What the break check says about a finding beyond the summary: the headline, the line at the base commit, the comment of the fix and the plugin code |
| `break-check/commit-check.spec.ts` | The break check on the commits of a repository that the spec creates |
| `compare-page/remote-source-tree.spec.ts`, `compare-page/github.spec.ts` | The break check on two commits that a stand-in for GitHub serves from a fixture case, and what the page asks GitHub for |
| `compare-page/page.spec.ts`, `compare-page/report-view.spec.ts`, `compare-page/content-cache.spec.ts` | The page from the address or the form to the report, the filter and the switches of the report, and the cache |
| `compare-page/finding-code.spec.ts` | The changed code and the code of the fix for findings of two example trees |
| `snapshot/committed-snapshot.spec.ts` | The real Administration: the committed snapshot is up to date and holds nothing machine-specific |
| `testing/fixture-runner.spec/`, `break-check/print-findings.spec.ts` | The fixture comparison and the summary of the break check |

`testing/example-components.ts`, `testing/example-services.ts`, `testing/example-stores.ts` and `testing/example-globals.ts` build the small source trees that the specs share.

Run them in the Administration folder:

```bash
npx jest --collectCoverage=false scripts/generate-public-api
```
