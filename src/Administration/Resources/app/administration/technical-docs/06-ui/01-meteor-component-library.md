# Meteor Component Library

## Overview

Meteor is Shopware's design system.
The Administration uses three of its packages, pinned in `package.json`:

- `@shopware-ag/meteor-component-library`: the Vue components and the design tokens.
- `@shopware-ag/meteor-icon-kit`: the icons.
- `@shopware-ag/meteor-admin-sdk`: the extension SDK, including the position identifiers that extensions target.

Meteor components replace the legacy `sw-*` base and form components.
New Administration UI uses Meteor components, tokens and icons.

## Documentation

The Meteor documentation at <https://meteor.shopware.com> is the source of truth for component APIs, tokens, icons and content guidelines.
Component names, props, slots, events, token names and icon names change between Meteor releases.
This file therefore names none of them and only describes how the Administration integrates Meteor.
Look them up every time, not from memory and not from older code.

### MCP server

Meteor serves its documentation through an MCP server, so AI assistants can read the current pages instead of relying on model memory.

```text
https://meteor.shopware.com/mcp
```

| Tool         | Description                                                                                       |
|--------------|---------------------------------------------------------------------------------------------------|
| `list-pages` | Lists all documentation pages with their paths and descriptions.                                  |
| `get-page`   | Returns one page. Component pages include the API reference and example code inlined as markdown. |

| Resource              | Description                                                 |
|-----------------------|-------------------------------------------------------------|
| `meteor://components` | Every component with its name, slug and description.        |
| `meteor://examples`   | Every documented example and its owning component.          |
| `meteor://icons`      | The full icon kit with name, mode and tags.                 |
| `meteor://tokens`     | Design tokens grouped by category, each with a description. |

Besides the component pages, the documentation covers design tokens, spacing, typography, icons, accessibility, the migration from legacy components, release notes, and content guidelines for wording, messaging and the glossary.

The server is not configured in this repository, so connect it in your own assistant.

```bash
# Claude Code
claude mcp add --transport http meteor https://meteor.shopware.com/mcp

# Several assistants at once
npx add-mcp https://meteor.shopware.com/mcp --name meteor
```

The [setup guide](https://meteor.shopware.com/documentation/getting-started/agents) lists the configuration for other tools.

### Without MCP

The same content is available as static files:

- <https://meteor.shopware.com/llms.txt> is an index of all pages.
- Every page is available as raw markdown under `https://meteor.shopware.com/raw/<path>.md`.

### Version drift

The documentation describes the latest Meteor release.
The Administration can be behind, so a documented component or prop may not exist in the pinned version.
Check the installed version in `package.json` and the registration described below before relying on something new.

## Integration in the Administration

### Global registration

Meteor components are registered globally, so templates use them by tag name.
Do not import them from `@shopware-ag/meteor-component-library` inside a component.

- `src/app/adapter/view/vue.adapter.ts` registers the library components directly, some of them as async components.
- `src/app/component/index.ts` registers the Administration wrappers from `src/app/component/meteor-wrapper/`.
- `src/app/main.ts` imports the library stylesheet and font, which also provide the design tokens.

A library component that is not registered in `vue.adapter.ts` is not available in templates yet.
Register it there in the same change when you need it.

### Experimental components

Meteor components are Experimental, Stable or Deprecated.
Experimental components can change or disappear in any release, without prior notice.
They are marked in the documentation with a warning at the top of the component page, and in the library code with `@experimental`.

Do not use experimental components in the Administration.
The status changes between releases, so check the component page before using a component you have not used before.
Some experimental components are registered in `vue.adapter.ts`, and a few existing templates use one.
That is not a reason to add new usages.

### Wrapper components

Some Meteor components are wrapped so the Administration can add extension points or admin-specific behavior.
The wrapper is registered under the original Meteor name, so templates do not change.
`src/app/component/meteor-wrapper/` contains the current wrappers, and each one describes its purpose in the docblock of its `index.ts`.

Wrappers that add extension points require a `position-identifier`.
They render `sw-extension-component-section` with it, so extensions can add their own content.
Position identifiers are public API for extensions.
Give every such component a unique one and do not rename existing ones.

## Styling

Design tokens are CSS custom properties from the library stylesheet.

- Use tokens instead of hard-coded colors, spacing, font sizes and border radii, and instead of the legacy SCSS variables.
- Choose a token by its meaning, not by its current value, so the UI works in light and dark mode.
- Take every token name from the Meteor documentation; never guess a name or derive one from another.
- Do not add fallback values inside `var()`.
- Do not override Meteor tokens.
  Overrides diverge from the design system and can break when token values change in a later release.
  Add a missing token with the `--sw-` prefix instead.
- Do not restyle Meteor internals through selectors on Meteor's own classes or `!important`.

## UX writing

The Meteor documentation also holds the content guidelines for Shopware products.
They cover wording and tone of voice, message patterns for error, validation, success, warning, info, empty and loading states, the glossary of approved English and German terms, and copy for AI features.

- Read them before writing or changing snippet text, in the same way as the component pages.
- Use glossary terms exactly as listed, in `en.json` and in `de.json`.
- Raise a term that the glossary does not cover instead of inventing one.

## Migration from legacy components

The legacy components stay available until their removal version.
Each one is marked with `@deprecated` in its `index` file, which names the Meteor replacement.
Do not add new usages of them.

A replacement is not a rename.
Props, slots and events differ, so read the component page and the migration page of the Meteor documentation before migrating a usage.
Legacy components without a stable Meteor replacement remain in use.

When you replace an `sw-*` component with a Meteor one in core, follow the deprecation rules in `coding-guidelines/administration/feature-flags-and-deprecations.md`.

## Resources

- Documentation: <https://meteor.shopware.com>
- Agents and MCP setup: <https://meteor.shopware.com/documentation/getting-started/agents>
- Repository: <https://github.com/shopware/meteor>
