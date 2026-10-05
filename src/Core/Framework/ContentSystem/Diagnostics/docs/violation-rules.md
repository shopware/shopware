# Violation Rules

## Unfilled required input

- `unfilledRequiredInputViolations` runs per resolution alongside `propertyBindingViolation` inside `bindingViolations`. It fires only on a required Reference resolved via `CandidateOrigin::Stored` (one satisfied by parent context or a loader candidate never reaches it). The reference resolves, yet the element would serve empty. It encodes the stored `DataRequirement` config (`DataLoaderConfigSerializerProvider::encode`) and reads the source's config specification (`AbstractContentSystemDataLoaderMapResolver::resolve()->configSpecificationFor`). It emits one `UnfilledRequiredInput` per **required** `propertyReference` config key whose configured (string) value names an element property with no stored value (`!hasStoredValue($element, $configuredProperty)`: absent key and authored null both count). Defaulted (`required: false`) `propertyReference` keys (the navigation shape) never gate. It keys on the input property (the control the Admin highlights) when it is a declared primitive, else on the reference property. The same neutral "has no value" message still names both keys. Stored wiring is never property-name validated. An undeclared storage key with an empty value is the normal pre-fill state of a resolvedBy reference, and a typo'd key is indistinguishable from it. No loader source name appears: the rule keys off `ConfigKeyKind::PropertyReference` only. `encode()` is not wrapped in a client-defect catch. A Stored resolution proves the loader and its serializer (via the prior decode that built the config object) are registered. A throw here is an internal fault that must surface.

## Unknown style option

- `UnknownStyleOption` (intrinsic, error) is reported once per `style` key of an element that the style option registry's strict `all()` does not know, keyed on the option name. It reports and never repairs; the write still rejects it. Which surface shows the code differs. The diagnose route and the draft mutation routes carry it in their 200 diagnostics body (the persisted mutation routes commit before diagnosing, so the write rejects first). The preview route turns it into an `elementTypesInvalid` 400 carrying the message (not the code) via `DraftLayoutChecker`. The DAL write never produces it, because the `Layout/Codec/StoredTreeConstraints` descriptor rejects an unknown option during `encode()`, which pre-empts the gate (see [Constraint errors at the write](#constraint-errors-at-the-write)).

## Stored requirement resolution

- `storedRequirementViolation()` runs one `resolveType()` call per stored `DataRequirement` the element carries (not only one recorded in `attributedSpecifications`) and reports exactly one outcome. A client-defect resolve failure is `InvalidConfig`. A resolve that succeeds but produces a type not assignable to the property's declared reference FQCN is `MismatchedReferenceType`. A resolve that succeeds and fits produces no violation (`Resolution/ElementResolver` reports it as a `Stored` resolution instead, never a `candidates` menu entry). `MismatchedReferenceType` is intrinsic, not binding-scope: the mismatch is a property of the element's own stored wiring, independent of any bound root source.

## Broken required chain

- `brokenChainViolations` reports a required consumer as `BrokenRequiredChain` unless an available entry supplies it. Keys match exact-or-dot-path via `ContextPathResolver::matches()`, the delivery predicate, so a required dotted consumer that delivery would resolve is not reported broken. The availability formula appends the root-ambient set at every depth, while delivery hands root-ambient values to root-scoped consumers alone. So a `parent`-scope consumer is supplied only by an entry with `root: false` and a `root`-scope consumer only by one with `root: true`. The message differs by consumer scope.

## Constraint errors at the write

- Field-serializer constraint errors throw before `PreWriteValidationEvent`, so `Validation/ContentLayoutWriteValidator` → `Validation/LayoutGate` → `LayoutDiagnostics` never runs and the response carries the constraint error alone.
