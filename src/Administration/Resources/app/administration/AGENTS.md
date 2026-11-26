# Shopware 6 Administration - AGENTS.md

> **Full Documentation**: See `technical-docs/` for comprehensive guides
> **Specific Areas**: See AGENTS.md in `src/core/`, `src/app/`, `src/module/`, `test/`
> **Skill**: For Admin JS/TS/Vue/Jest work, follow `shopware-admin-js` (`.agents/skills/shopware-admin-js/SKILL.md`) — it carries the mandatory coding + test rules.

## File Structure
```
technical-docs/     # Full technical documentation
src/
├── core/               # Vue indepedent code, Framework, repositories, services (AGENTS.md)
|   ├── application.ts  # Application bootstrap (AGENTS.md)
|   └── shopware.ts     # Global Shopware object in window (AGENTS.md)
├── app/                # Vue specific code, UI, components, stores (AGENTS.md)
│   ├── init/           # Boot sequence (AGENTS.md)
│   ├── component/      # Global components (AGENTS.md)
│   └── store/          # Pinia stores (AGENTS.md)
└── module/             # Business modules (AGENTS.md)
test/                   # Only setup files, helper and mocks for tests (AGENTS.md)
```

## Technologies
```
# Core
TypeScript          # Main programming language
JavaScript          # Was used in legacy code
Vue 3               # Components get compiled to Vue 3 components
Twig.JS             # Templating engine to allow extensible Vue components
Pinia               # State management
Vue Router          # Routing
Axios               # HTTP client
Vite                # Build tool
Jest                # Testing framework
Meteor              # Design system: mt-* components, design tokens, icon kit
```

## Special differences to regular Vue projects

- **Component Factory and Runtime Vue Components**: The application uses a component factory that allows dynamic extensibility. This factory creates real Vue components at runtime, which is why the project does not use Single File Components (SFCs). Instead, components are registered dynamically, enabling plugins and extensions to modify or extend existing components seamlessly.
  - **Reference**: See `src/Administration/Resources/app/administration/technical-docs/03-extensibility/` for details on the component factory and extensibility.

- **Special Boot Sequence**: The boot process is tailored to the Shopware ecosystem. It includes steps such as `initState`, `registerConfig`, and `initializeFeatureFlags`. The sequence dynamically imports core modules like `core/shopware.ts` and `app/main.ts`, initializes the dependency injection container, and sets up services and plugins. The Twig shell is used to inject runtime configurations before the Vue.js application is bootstrapped.
  - **Reference**: See `src/Administration/Resources/app/administration/technical-docs/02-architecture/01-boot-process.md` for a detailed overview of the boot sequence.

- **Global Shopware Object**: A global `Shopware` object is created during the boot process. This object acts as the central point for accessing services, factories, and the dependency injection container. It is initialized in `core/shopware.ts` and is available throughout the application.
  - **Reference**: See `src/Administration/Resources/app/administration/technical-docs/02-architecture/03-module-system.md` for more information on the global Shopware object.

## UI components, styling and UX writing (Meteor)

The Administration UI is built with the [Meteor Design System](https://meteor.shopware.com).
Meteor is the source of truth for components, tokens, icons and UX writing.
Component names, props, slots, events, token names, icon names and glossary terms change between releases, so this file names none of them.
Look them up every time, not from memory and not from the code next to yours.

**Docs:** Use the `meteor` MCP server (`https://meteor.shopware.com/mcp`).
`list-pages` lists every documentation page, and `get-page` returns one page, for a component including its API reference and examples.
The resources `meteor://components`, `meteor://examples`, `meteor://icons` and `meteor://tokens` return the full catalogs.
The server is not configured in this repository, so connect it in your own tool, for Claude Code with `claude mcp add --transport http meteor https://meteor.shopware.com/mcp`.
Fallback: [llms.txt](https://meteor.shopware.com/llms.txt) or `https://meteor.shopware.com/raw/<path>.md`.
Other tools: [setup guide](https://meteor.shopware.com/documentation/getting-started/agents).
The docs describe the latest Meteor release, while the Administration pins its versions in `package.json`.

**Components:** Use an existing Meteor component whenever one fits, through its documented props, slots and variants.
Do not add new usages of a legacy `sw-*` component that is deprecated in favor of a Meteor component; its `@deprecated` docblock names the replacement.
Meteor components are registered globally in `src/app/adapter/view/vue.adapter.ts` and through the wrappers in `src/app/component/meteor-wrapper/`.
Use them by tag name; do not import them from the library inside a component, and check that a component is registered before using it.
Do not use a component that Meteor marks as experimental, in the docs (a warning at the top of the component page) or in the library code (`@experimental`), because its API can break without notice.
Check the status on the component page each time, because it changes between releases.
Do not restyle Meteor internals (selectors on Meteor's own classes, `!important`).
Build a custom component only when nothing fits, from Meteor tokens, icons and components.

**Tokens:** Use semantic Meteor design tokens for color, typography, spacing and border radius, chosen by meaning so dark mode works.
Take every token name from the Tokens page or the `meteor://tokens` resource; never guess a name or derive one from another.
In new or changed code, use no hex or rgb values, no fallback values inside `var()` and no legacy SCSS variables.
Never override Meteor tokens; add a missing one with the `--sw-` prefix and mention it in the PR.

**Icons:** Use the Meteor icon component with a name from the Meteor icon kit, no custom SVGs or other icon libraries.
Follow the Icons page for variant, size and color, and pair every icon with a label or an accessible name.

**UX writing:** Before you write or change user-facing text, read the content guidelines in the Meteor docs.
They cover wording and tone of voice, message patterns (error, validation, success, warning, info, empty and loading states), the glossary of approved English and German terms, and copy for AI features.
This applies to every snippet text: labels, buttons, titles, help texts, notifications and empty states.
Use glossary terms exactly as listed, in `en.json` and in `de.json`, and do not invent alternatives.

Existing code still has hard-coded colors, fallback values, legacy `sw-*` components, experimental components and overrides of Meteor classes.
Migrate what you touch when it is low-risk, without unrelated restyling.

Ask first before you add a custom component where Meteor almost fits, a new `--sw-` token, a non-Meteor icon, a usage of an experimental component or a term that the glossary does not cover.

**See**: `technical-docs/06-ui/01-meteor-component-library.md` for how the Administration registers and wraps Meteor components.

## Coding guidelines
- Write Jest tests for all new features and bug fixes
  - Locate tests in the same folder as the code they are testing, using the `.spec.ts` suffix.
  - Split big tests (500+ lines) into a `.spec/` directory named after the original spec. Each file in that directory must cover a logical scenario or behavior group, similar to how related tests are grouped with `describe` blocks, for example `sw-component-name.spec/validation.spec.ts`. See `adr/2026-05-06-split-large-administration-test-files.md`.
- When using feature flags in Jest tests, you must consult `adr/2026-08-06-administration-jest-feature-flag-helpers.md`
- Use TypeScript for all new code
- Do NOT introduce breaking changes to public APIs without prior discussion
- Model new code on these reference implementations, not on the code next to it, which often predates our current rules:
  - Component with spec: `src/app/component/media/sw-media-folder-thumbnail/`
  - Composable with spec: `src/app/composables/use-theme.ts`
  - Pinia store with spec: `src/app/store/tabs.store.ts`
- Use the provided linting and formatting scripts (see below)
- Existing ESLint violations live in `eslint-suppressions.json`, which may only shrink: fix every violation your change adds, see `technical-docs/11-code-quality/01-lint-gate.md`
- `npm run lint:fallow` reports unused files, exports and dependencies.
  Tag reported exports that extensions use with `@public`, see `technical-docs/11-code-quality/02-unused-code.md`

## Scripts
Run the composer commands in the root of the repository. These commands are wrapper scripts around the NPM scripts.
In a Docker environment prepend `docker compose exec web ...`.

```bash
# Linting
composer eslint:admin # Run ESLint
composer eslint:admin:fix # Run ESLint with --fix
composer fallow:admin # Run Fallow
composer fallow:admin:prune # Remove fixed entries from the Fallow baseline
composer stylelint:admin # Run Stylelint
composer stylelint:admin:fix # Run Stylelint with --fix

composer format:admin # Format code with Prettier
composer format:admin:fix # Format code with Prettier and --write

# Tests
composer admin:unit # Run unit tests
composer admin:unit:watch # Run unit tests in watch mode

# Single jest test, run inside "src/Administration/Resources/app/administration" folder
npx jest --collectCoverage=false src/core/factory/http.factory.spec.js # Example single test run
# All jest tests without coverage for better readability, run inside "src/Administration/Resources/app/administration" folder
npx jest --collectCoverage=false

# Build
composer build:js:admin # Build the administration
```

**See**: `src/Administration/Resources/app/administration/technical-docs/` for architecture, patterns, and detailed guides
