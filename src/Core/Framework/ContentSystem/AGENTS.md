## Navigation

- Reference documents index: [docs/README.md](docs/README.md)
- Why the module's design rules hold, and what was not chosen: [docs/principles/README.md](docs/principles/README.md)
- Admin preview API and its wire contract: [Api/AGENTS.md](Api/AGENTS.md), [Api/docs/preview-url.md](Api/docs/preview-url.md)

## Constraints

- Before planning or making a change, read the principle file for the area the change touches ([docs/principles/README.md](docs/principles/README.md) routes by task and by area). Check: does the change contradict a rule, a Why line or a Not chosen line of that file?
- A change to a rule changes its pinning test in the same commit. The pinning test is the enforcing class's test, unless the rule's In code list names another.
- `RenderingSpecificationResolver`: iterates sources via `supports()` bool check, first match wins — NOT null-return
- OpenAPI schemas: update `src/Core/Framework/Api/ApiDefinition/Generator/Schema/StoreApi/` when modifying endpoints
- Constraints owned by a reference document: pipeline step order — [docs/pipeline-steps.md](docs/pipeline-steps.md); introspection assembly and the `storageSchema` fold — [docs/introspection-endpoints.md](docs/introspection-endpoints.md); primitive property satisfaction and what each write-time gate admits — [docs/layout-write-gates.md](docs/layout-write-gates.md); which error codes count as a client defect — [docs/client-defect-codes.md](docs/client-defect-codes.md)

## Quick Reference

- Exception class: `ContentSystemException`
- Package: `#[Package('framework')]`
- DAL: Use Criteria API + EntityDefinition, NOT Doctrine ORM
- DI config: `content-system.php` contains only framework-owned infrastructure; domain-specific services are registered in their owning module's DI
