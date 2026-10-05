# Failure and loss

The rules in this area apply across the module and govern how it handles a defect and content that an operation cannot keep.

## A component throws where it meets invalid data, and nothing degrades silently

Every component fails fast on invalid, unrepresentable or misconfigured data, at the point where it meets that data. No component skips, coerces or swallows invalid, unrepresentable or misconfigured data. No path degrades into an output that looks like a valid result. No change loosens a value-domain check to make a suite pass.

Why: A skipped defect appears later as a state that no write could produce. A degraded result that looks valid cannot be told from a correct one, so no caller can react to it.

Decided against: A first-wins pick or a precedence rule that resolves an ambiguous entity-name derivation or an ambiguous default specification into a value.

Exceptions: A data loader degrades to a not-found result on a collaborator's HTTP exception, under the [degradation rule](data-loading.md#a-loader-degrades-on-a-named-domain-outcome-and-lets-every-other-fault-propagate). It degrades on an entity id that `Uuid::isValid()` rejects, so an unsubstituted placeholder never reaches an id parser. Each database registry loader skips and logs a malformed row under the [build-time rule](#a-declaration-or-registration-defect-fails-the-build-or-the-load-never-a-request). The attribution reconciler drops a diverged attribution, as [extension-surface.md](extension-surface.md#an-app-shipped-declaration-is-validated-and-reconciled-by-the-module-never-trusted-or-patched) states. The type declaration reader reads the `meta`, `properties` and `slots` keys only and ignores every other top-level key. It stays lenient because the binding system reads the inline `bindings:` section of the same file.

In code:

- `RenderedElement` admits a scalar, null, an array of those, a `Struct`, a `\DateTimeInterface` or a `\BackedEnum` as a property value. It throws `unsupportedPropertyValueType()` on anything else.
- `SlicedDistributionConfig` rejects a slice size below 1 instead of clamping it.
- `ResolvedValueIndexEncoder` throws on a missing index, because an empty map would look like a page whose elements resolved nothing.
- See [DataLoader/README.md](../../Hydration/DataLoader/README.md#degradation-boundary).

## A declaration or registration defect fails the build or the load, never a request

The registry load fails fast on a property-key collision, on `resolvedBy` on a primitive or on `enum` on a non-primitive. The container build fails fast on a loader with no config serializer, an abstract loader class, an unparseable `@extends` or a duplicate source. Decoration is the only way to replace a data loader, and registration order never replaces one. Install and update of an app fail on a malformed row, as [extension-surface.md](extension-surface.md#an-app-shipped-declaration-is-validated-and-reconciled-by-the-module-never-trusted-or-patched) states. The production load skips and logs a malformed persisted row. It never invents a name for a malformed persisted row.

Why: A request-time defect fails that request. If one loader rejects a row that another skips, the same row fails the load or is left out. A persisted row can turn invalid after install, for example when a dependency is deactivated. The production load skips that row instead of failing every request.

In code:

- `YamlTypeLoader`, `DefaultBindingSpecificationSynthesizer` and `ContentSystemDataLoaderCompilerPass` enforce the load and build failures.
- See [architecture.md](../../Layout/Type/docs/architecture.md).

## The module drops nothing silently and reports every loss

The mutation response returns a detached child as orphaned and names every dropped property and every dropped wiring key. The server answers an undeclared field with a 400. It answers a request affordance that the route does not support with a 400. It strips neither. A request DTO accepts no extra attributes. An editor that reads the orphaned child and the dropped names from the mutation response can offer re-placement, undo or confirmation. An editor that discards them promotes silent loss.

Why: A client that cannot tell applied from ignored changes keeps editing as if all were applied. The author then loses content without being told.

Exceptions: A whole-subtree remove reports no orphans, because the removal is itself the request.

In code:

- `MutationResponse` carries `LayoutMutation::orphaned()`, `droppedWiring()` and `droppedProperties()`.
- `ContentRoute` and `UnknownRequestFieldExceptionListener` produce the 400s.
- See [mutation-response.md](../../Api/docs/mutation-response.md).

## The cause of a defect sets its classification, and HTTP status is a separate axis

A code that a client can cause is a [client defect](README.md#glossary), and `CLIENT_DEFECT_CODES` lists it. A defect whose only possible cause is data bypassing the write gate is an internal fault, and `CLIENT_DEFECT_CODES` does not list it. The module never repairs an internal fault. The route or serializer that meets a defect sets the HTTP status of that defect on its own path. A mutation payload defect reaches the client as a 400 with a code and a detail, never as a 500 or a silent save.

| Path that meets a defect in an id or in a layout | HTTP status or behaviour |
|---|---|
| DAL write | 400 |
| Strict draft decode | throws |
| Diagnose | reports a violation |
| Stored-column read | 500 |

Why: A status derived from `CLIENT_DEFECT_CODES` would make a read of stored corruption a 400 that marks the client as the cause.

Exceptions: `CLIENT_DEFECT_CODES` lists neither the mutation structural 400s nor the field-selection 400. A mutation payload that its request object rejects is a 400 without an error code.

In code:

- `StoredElementListFieldSerializer` wraps module exceptions at the write boundary as 400s.
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
