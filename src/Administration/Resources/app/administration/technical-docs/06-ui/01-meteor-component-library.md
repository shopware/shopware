# Meteor Component Library

## Overview

Meteor is Shopware's design system.
The Administration uses `@shopware-ag/meteor-component-library` for components and design tokens, `@shopware-ag/meteor-icon-kit` for icons and `@shopware-ag/meteor-admin-sdk` for extensions, pinned in `package.json`.

## Documentation

The [Meteor documentation](https://meteor.shopware.com) is the source of truth for components, tokens, icons and content guidelines.
AI assistants read it through the MCP server at `https://meteor.shopware.com/mcp`, see the [setup guide](https://meteor.shopware.com/documentation/getting-started/agents).
The documentation describes the latest release, which can be ahead of the pinned version.

## Integration in the Administration

- `src/app/adapter/view/vue.adapter.ts` registers the library components globally, so templates use them by tag name.
- `src/app/component/meteor-wrapper/` contains wrappers that add extension points or admin-specific behavior, registered under the original Meteor name.
- `src/app/main.ts` imports the library stylesheet, which provides the design tokens.

Do not use components that Meteor marks as experimental, in the documentation or with `@experimental` in the library code.

## Migration from legacy components

Legacy `sw-*` components replaced by Meteor are marked `@deprecated` and name their replacement.
Do not add new usages.
Props, slots and events differ, so check the component page before migrating, and follow `coding-guidelines/administration/feature-flags-and-deprecations.md` in core.
