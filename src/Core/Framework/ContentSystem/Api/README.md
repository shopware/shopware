# Api

Admin API controllers for the content system. Store API rendering lives in `SalesChannel/`; this directory holds Admin-scoped (`ApiRouteScope`) endpoints: layout preview, resolve-and-diagnose, the nine stateless draft mutation actions, and the nine persisted mutation actions that commit one edit to a stored layout.

## What Lives Here

Four controllers sit behind the Admin API's content-system actions: one mints a preview URL, one resolves and diagnoses a draft, and two apply structural operations — one to a tree the request carries, one to a stored layout. They share a request decoder and a response shape rather than each modelling their own, which is what keeps a draft edit and a committed edit answering in the same form.

The class index lives in [AGENTS.md](AGENTS.md); the per-subject references are indexed from its `## Navigation` section, and [docs/routes.md](docs/routes.md) lists every route with its name and OpenAPI file.

## Endpoint Reference

- [docs/workflow.md](docs/workflow.md) - How introspection, mutation, diagnose, and preview chain together while a client builds a layout.
- [docs/preview-url.md](docs/preview-url.md) - The preview action that mints a short-lived, openable URL for a draft layout: route, request envelope, response, and error model.
- [docs/diagnose.md](docs/diagnose.md) - The resolve-and-diagnose action: route, request envelope, and error model.
- [docs/diagnose-response.md](docs/diagnose-response.md) - The diagnose response body: resolutions, diagnostics, and the violation codes.
- [docs/mutation.md](docs/mutation.md) - The nine stateless draft mutation actions and the request envelope they share.
- [docs/mutation-response.md](docs/mutation-response.md) - The seven-key response body every stateless mutation action returns.
- [docs/mutation-errors.md](docs/mutation-errors.md) - The failure conditions that abort a stateless mutation instead of being reported in diagnostics.
- [docs/mutation-binding.md](docs/mutation-binding.md) - Applying a binding specification through `bind-element` or `insert-element`, and the automatic default the scaffold applies.
- [docs/persisted-mutation.md](docs/persisted-mutation.md) - The nine persisted mutation actions, their request envelope, and their committed response.
- [docs/persisted-mutation-errors.md](docs/persisted-mutation-errors.md) - The failure conditions specific to the persisted mutation actions.

The introspection endpoints that feed these are documented with the systems they describe: element types in [../Layout/Type/docs/introspection.md](../Layout/Type/docs/introspection.md), style options in [../Layout/Element/Style/docs/introspection.md](../Layout/Element/Style/docs/introspection.md), data loaders in [../Hydration/DataLoader/docs/introspection.md](../Hydration/DataLoader/docs/introspection.md), and assignable entity types in [../Adapter/docs/introspection.md](../Adapter/docs/introspection.md).
