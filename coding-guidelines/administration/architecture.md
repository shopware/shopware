# Administration architecture

These rules apply to code under `src/Administration/Resources/app/administration`.

## Layers

- Model new code on the reference implementations listed in `src/Administration/Resources/app/administration/AGENTS.md`, not on the code next to it.
- Do not introduce breaking changes to public Administration APIs or extension points without prior discussion.
- Use the repository-root composer wrappers for Administration linting, formatting, tests, and builds.
- Keep `core` free of Vue-related code. Modules may import shared non-Vue functionality from `core`.
- Keep module code independent. Do not import directly from another module; communicate through registered services, repositories, routes, stores, or shared app/core code.
- Keep the boot order `init-pre/ -> init/ -> init-post/` when changing startup code.

## Extension-aware access

- Prefer extension-aware access through the global `Shopware` APIs where they are available, for example `Shopware.Component`, `Shopware.Service()`, and `Shopware.Store`. Component code may still use injected services, and boot code may need direct access before all globals are available.
- Do not import factory internals directly when an extension-aware global API is available in that context.
- Register Options API components with Twig blocks through the component factory. Vue SFC components that use the new extension system and native blocks may be registered natively.
- Preserve extension points exposed through the global `Shopware` object when changing repositories, services, components, and stores.

## Modules and UI

- Protect module routes, navigation entries, and templates with the required ACL privileges.
- When adding Admin UI that reads or persists a DAL entity or association, update the matching ACL privilege mapping in the same change. Verify limited-role users get the needed `read`, `create`, `update`, and `delete` privileges through the feature's viewer/editor/creator/deleter roles instead of relying on super-admin behavior.
- If new privileges must apply to roles that already exist in installations, add a migration that updates stored `acl_role.privileges`; changing the Administration mapping alone only fixes future role evaluation.
- Reload an entity after saving it through a repository so origin state and change tracking stay in sync.
- Use snippets for visible text; do not hardcode user-facing strings.
- Write Administration copy according to the content guidelines in the [Meteor documentation](https://meteor.shopware.com): wording, messaging, and the glossary of approved English and German terms.
  Check them before writing or changing snippet text, and use glossary terms exactly as listed.
- Keep business logic out of templates.
- Prefer composables for new shared Vue logic. Do not add new mixin-based APIs unless you are extending legacy code.
- Build UI from Meteor components.
  Do not add new usages of legacy `sw-*` components that are deprecated in favor of a Meteor component, and do not rebuild a control that Meteor provides.
- Do not use Meteor components that are marked as experimental in the Meteor documentation or with `@experimental` in the library code.
  Their API can change without notice.
- Use Meteor components by their globally registered tag name.
  Do not import them from `@shopware-ag/meteor-component-library` inside a component.
- Use BEM-style class names and Meteor design tokens for Administration styling.
  Avoid inline styles and hard-coded colors, spacing, and font sizes.
- Take component props, slots, events, token names, and icon names from the [Meteor documentation](https://meteor.shopware.com), not from memory or older code.
  They change between Meteor releases, which is why these guidelines do not list them.
  AI assistants read the documentation through the Meteor MCP server at `https://meteor.shopware.com/mcp`; setup is described in `src/Administration/Resources/app/administration/technical-docs/06-ui/01-meteor-component-library.md`.

## Data access

- Use repositories and Criteria for entity work instead of direct entity or HTTP manipulation.
- Keep Criteria page sizes reasonable and disable total counts when the UI does not need them.
- Load only the associations needed for the current screen or operation.
- Batch related entity changes into one save when possible.
