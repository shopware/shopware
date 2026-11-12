# Failure and loss

The rules in this area apply across the module and govern how it handles a defect and content that an operation cannot keep.

## A component throws where it meets invalid data, and nothing degrades silently

Every component fails fast on invalid, unrepresentable or misconfigured data, at the point where it meets that data. No component skips, coerces or swallows invalid, unrepresentable or misconfigured data. No path degrades into an output that looks like a valid result. No change loosens a value-domain check to make a suite pass.

Why: A skipped defect appears later as a state that no write could produce. A degraded result that looks valid cannot be told from a correct one, so no caller can react to it.

Decided against: A first-wins pick or a precedence rule that resolves an ambiguous entity-name derivation or an ambiguous default specification into a value.

Exceptions: Mapping missing/empty values use documented fallback or token removal, and withdrawn/incompatible mappings are omitted. These are explicit [mapping outcomes](data-mapping.md#admission-and-missing-data-are-separate-outcomes); provider errors and parser/output-limit faults still abort the pipeline. The Storefront may catch the outer failure, while Store API and preview propagate it. A data loader degrades to a not-found result on a collaborator's HTTP exception, under the [degradation rule](data-loading.md#a-loader-degrades-on-a-named-domain-outcome-and-lets-every-other-fault-propagate). It degrades on an entity id that `Uuid::isValid()` rejects, so an unsubstituted placeholder never reaches an id parser. The attribution reconciler drops a diverged attribution, as [extension-surface.md](extension-surface.md#an-app-shipped-declaration-is-validated-and-reconciled-by-the-module-never-trusted-or-patched) states. The type declaration reader reads the `meta`, `properties` and `slots` keys only and ignores every other top-level key. It stays lenient because the binding system reads the inline `bindings:` section of the same file.

In code:

- `RenderedElement` admits a scalar, null, an array of those, a `Struct`, a `\DateTimeInterface` or a `\BackedEnum` as a property value. It throws `unsupportedPropertyValueType()` on anything else.
- `SlicedDistributionConfig` rejects a slice size below 1 instead of clamping it.
- `ResolvedValueIndexEncoder` throws on a missing index, because an empty map would look like a page whose elements resolved nothing.
- See [DataLoader/README.md](../../Hydration/DataLoader/README.md#degradation-boundary).

## A declaration or registration defect fails the container build or the registry load, before any request uses the declaration

A malformed declaration, from a file or a persisted row, fails the load of its registry. No registry loader skips a declaration or invents a name for it. A defect in a data loader registration, a second loader for an existing source among them, fails the container build, so registration order never replaces a loader.

Why: A defect that surfaces where a request uses the declaration fails far from its cause. A registry loads on first use and fails as a whole, while a skipped row would leave it silently without the declaration. The accepted cost: one malformed persisted row fails every load of its registry until the row is fixed or its app is deactivated.

Decided against: Skipping and logging a malformed persisted row so that the rest of the registry loads.

In code:

- `YamlTypeLoader`, `ContentSystemDataLoaderCompilerPass` and the four database registry loaders, `DatabaseTypeLoader` among them, enforce the load and build failures.
- `DefaultBindingSpecificationSynthesizer` fails the load on `resolvedBy` on a non-reference property and on a storage-key collision, and `TypedEnumValidator` on `enum` without exactly one primitive type.
- `DatabaseTypeLoaderTest::testValidatesAllRowsTogetherAndFailsTheWholeLoad` pins that one invalid row fails the whole load.
- See [architecture.md](../../Layout/Type/docs/architecture.md).

Exceptions: Curated mapping candidates have a defined runtime collision policy: exclude every owner of the ambiguous name and warn, while unrelated candidates remain available. Stable declared collisions fail compilation; provider load faults still propagate. This is separate from skipping malformed installed declaration rows. See [mapping overrides and collisions](data-mapping.md#intentional-overrides-cannot-skip-source-admission).

## The module drops nothing silently and reports every loss

The mutation response returns a detached child as orphaned and names every dropped property and every dropped wiring key. The server answers an undeclared field with a 400. It answers a request affordance that the route does not support with a 400. It strips neither. A request DTO accepts no extra attributes. An editor that reads the orphaned child and the dropped names from the mutation response can offer re-placement, undo or confirmation. An editor that discards them promotes silent loss.

Why: A client that cannot tell applied from ignored changes keeps editing as if all were applied. The author then loses content without being told.

Exceptions: A whole-subtree remove reports no orphans, because the removal is itself the request.

In code:

- `MutationResponse` carries `LayoutMutation::orphaned()`, `droppedWiring()` and `droppedProperties()`.
- `ContentRoute` and `UnknownRequestFieldExceptionListener` produce the 400s.
- See [mutation-response.md](../../Api/docs/mutation-response.md).

## The cause of a defect sets its classification, and HTTP status is a separate axis

A code that a client causes through the element tree is a [client defect](README.md#glossary), and `CLIENT_DEFECT_CODES` lists it. A code that a client causes through a route parameter, a mutation operation or another request field stays off the list. So does an internal fault, a defect whose only possible cause is a bug in core or in a plugin, or data that bypassed the write gate. The module never repairs an internal fault. The route or serializer that meets a defect sets the HTTP status of that defect on its own path. A mutation payload defect reaches the client as a 400 with a code and a detail, never as a 500 or a silent save.

| Path that meets a defect in an id or in a layout | HTTP status or behaviour |
|---|---|
| DAL write | 400 |
| Strict draft decode | throws |
| Diagnose | reports a violation |
| Stored-column read | 500 |

Why: A status derived from `CLIENT_DEFECT_CODES` would make a read of stored corruption a 400 that marks the client as the cause.

Exceptions: Mapping admission findings use `MappingProblem`/`InvalidMapping` and dedicated write error codes, not the generic `CLIENT_DEFECT_CODES` catch path. See [mapping validation](../client-defect-codes.md#mapping-validation). `INVALID_LAYOUT_STRUCTURE` marks a structural defect of a submitted tree, such as a repeated element id, and stays off `CLIENT_DEFECT_CODES`. A mutation payload that its request object rejects is a 400 without an error code.

In code:

- `StoredElementListFieldSerializer` wraps a client defect and its own duplicate-id rejection as a 400 write rejection. Any other module exception met during the write keeps its own status, so a definition-load failure answers 500 and a registry conflict 409.
- `DraftLayoutDecoder` and `LayoutDiagnostics` read `isClientDefect()`.
- `DraftLayoutDecoder::decode()` remaps a client defect to `invalidLayoutStructure`.
- `UnknownRequestFieldExceptionListener` remaps an unknown field to `unknownRequestField`.
- `ContentSystemExceptionTest` pins the client-defect reads.
- [mutation-errors.md](../../Api/docs/mutation-errors.md) lists each mutation error with its status and code.
- See [client-defect-codes.md](../client-defect-codes.md).

## One exception class holds a closed, pinned set of client-defect codes

Each code has one factory that new callers reuse. The module has one exception class, and its `CLIENT_DEFECT_CODES` is the one list of codes that mark a client defect. `CLIENT_DEFECT_CODES` is a public constant that only grows. A test pins its exact membership. In one change, a new code enters the constant, the pinned list, the documentation and the administration's own enumeration of the codes it acts on. The administration's `structuralErrorCodes` lists the structural codes of the mutation operations. A change that adds a structural code of a mutation operation extends that list and [mutation-errors.md](../../Api/docs/mutation-errors.md) together. A changed value in `CLIENT_DEFECT_CODES` is a compatibility break to report. A rejection about an element carries that element's id.

Why: The administration gives a specific message only to codes it lists, and others get a generic one. Two codes for one condition make each consumer match both. Without the element id, the editor must search for the element.

Decided against: An exception class, a catalogue or a code per feature or per call site.

Exceptions: `unknownStyleBreakpoint()` and `invalidLoaderConfig()` each reuse the code of another factory. `unknownStyleBreakpoint()` reuses its code by documented design. The codec throws the wiring factories that the render step also throws, without an element id.

In code:

- `ContentSystemException` holds the catalogue.
- The administration's `structuralErrorCodes` lists the mutation error codes that it maps to a message.
- See [client-defect-codes.md](../client-defect-codes.md).
