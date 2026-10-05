## Constraints

- Before planning or making a change, read the principle file for the area the change touches ([docs/principles/README.md](docs/principles/README.md#index) routes by task and by area). Check: does the change contradict a rule, a Why line or a Not chosen line of that file?
- Change a rule, its pinning test and the code it governs in the same commit. The pinning test is the enforcing class's test, unless the rule's In code list names another ([principle files](docs/principles/README.md#by-area)). Check: the test is in the diff.
- Update the Store API OpenAPI schema when changing an endpoint. Check: the matching file under [`Framework/Api/ApiDefinition/Generator/Schema/StoreApi/`](../Api/ApiDefinition/Generator/Schema/StoreApi/) is in the diff.
- Register a domain service in its owning module's DI, never in `content-system.php`. Check: [Domain Placement](README.md#domain-placement) lists the class as domain-owned.

## Where to look

- Reference documents, one subject per file: [docs/README.md](docs/README.md#contentsystem-reference-documents)
- Terms easy to confuse, glossary and module map: [docs/principles/README.md](docs/principles/README.md#terms-that-are-easy-to-confuse)
- Content sections, rendering pipeline overview and key classes: [README.md](README.md#content-sections)
- Domain-owned versus framework-owned classes, DI registration and the package attribute: [README.md](README.md#domain-placement)
- Specification source selection by the resolver: [Adapter/docs/custom-sources.md](Adapter/docs/custom-sources.md#chain-of-responsibility)
- Pipeline step order, the orderings inside preparation, and the data flow diagram: [docs/pipeline-steps.md](docs/pipeline-steps.md#pipeline-steps), [docs/data-flow.md](docs/data-flow.md#rendering-data-flow)
- Naming a type, the Stored/Rendered prefix and what each role suffix promises: [NAMING.md](NAMING.md#a-name-answers-two-questions), [docs/stored-and-rendered.md](docs/stored-and-rendered.md#where-each-subject-belongs), [docs/role-suffixes.md](docs/role-suffixes.md#the-two-model-roles)
- Extension mechanisms and where each is authored: [docs/extending.md](docs/extending.md#extending-the-content-system)
- DI tags, base classes, value objects, enums, events and the exception class: [docs/service-tags-and-types.md](docs/service-tags-and-types.md#service-tag-reference), [docs/service-tags-and-types.md](docs/service-tags-and-types.md#type-reference)
- Which error codes count as a client defect: [docs/client-defect-codes.md](docs/client-defect-codes.md#client-defect-error-codes)
- Introspection assembly and the `storageSchema` fold: [docs/introspection-endpoints.md](docs/introspection-endpoints.md#what-the-endpoints-derive-from)
- Primitive property satisfaction, what each write-time gate admits and delete protection: [docs/layout-write-gates.md](docs/layout-write-gates.md#layout-write-gates)
- The stateless and the persisted structural-edit runners: [docs/layout-mutation.md](docs/layout-mutation.md#layout-mutation)
- What one binding specification declares and its two apply modes: [docs/binding-specifications.md](docs/binding-specifications.md#binding-specifications)
- Element style options, storage and serving: [docs/element-styles.md](docs/element-styles.md#element-styles)
- A worked layout combining entity rendering, data loading and context distribution: [docs/product-detail-page.md](docs/product-detail-page.md#product-detail-page-example)
- Admin preview API, its route constraints and its wire contract: [Api/AGENTS.md](Api/AGENTS.md#constraints), [Api/docs/preview-url.md](Api/docs/preview-url.md#preview-url)
- Data access in this repository: [AGENTS.md](../../../../AGENTS.md#not-standard-symfonydoctrine)
