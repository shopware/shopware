## Constraints

- Add no protected member, subscribed service or import to `StorefrontController` or another base plugin controllers extend. Check: does the change touch a plugin-extended base? [extension-surface.md](../docs/principles/extension-surface.md#the-module-adds-nothing-to-a-platform-base-class-plugins-extend)

## Where to look

- Endpoints, field-selection rejection and `?elementId` gating: [README.md](README.md#endpoints)
- Route registration and the generated `ContentRoute` services: [README.md](README.md#route-registration)
- Route decoration and the per-format answers the route hands the pipeline: [README.md](README.md#key-classes)
- Which work the route does and which the pipeline does: [pipeline-steps.md](../docs/pipeline-steps.md#pipeline-steps)
