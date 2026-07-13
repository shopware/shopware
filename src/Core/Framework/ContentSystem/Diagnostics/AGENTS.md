@README.md

## Source Code References

- `LayoutDiagnostics` - Entry point. `analyze(array $tree, ?array $rootContext): LayoutAnalysis`. Flattens the element tree, runs the duplicate-id check once as a cross-element batch pass over the flattened set, then the per-element intrinsic checks (unregistered component, invalid config, mismatched reference type on stored wiring, orphaned provider) on every element unconditionally, then binding checks only when `$rootContext !== null`.
- `LayoutAnalysis` - Output of `analyze()`. `public DiagnosticsReport $report` and `public array $resolutions` (keyed by element id, values are `list<PropertyResolution>`). `@internal final readonly`.
- `DiagnosticsReport` - Holds `list<Violation> $violations`. `isWellFormed(): bool` (no intrinsic-scope Error violations — persistence gate). `isResolvable(): bool` (no binding-scope Error violations — serving gate). Also `intrinsicErrors()`, `bindingErrors()`. `@internal final readonly`.
- `Violation` - `@internal final readonly`. Constructor: `ViolationCode $code, string $elementId, ?string $key, string $message, list<ResolutionCandidate> $candidates = []`. `scope()` and `severity()` delegate to the code.
- `ViolationCode` - `enum: string`, 10 cases. Single source of truth for scope and severity. See README for the full mapping table.
- `ViolationScope` - `enum: string`. Cases: `Intrinsic`, `Binding`. `@internal`.
- `ViolationSeverity` - `enum: string`. Cases: `Error`, `Warning`. `@internal`.
- `RootContextMapper` - `map(array<DataRequirement> $requirements): list<ProvidedContext>` maps a bound source's page data requirements to the root-ambient context for `analyze()` (each context broadcast `Single` from the virtual root). Also exposes `resolveType(DataRequirement): string`, used by the diagnostics core to detect `InvalidConfig`.

## Constraints

- `LayoutAnalysis` constructor order: `report` first, then `resolutions`.
- Binding checks are skipped when `$rootContext` is `null`; only the intrinsic subset runs.
- `ViolationCode` is the only place scope and severity are defined — do not derive them anywhere else.
- Primitive-property satisfaction is strict: `propertyBindingViolation` flags a required primitive as `UnresolvedRequired` iff `$element->getProperty($key) === null`. Do not reintroduce a `|| $resolution->default === null` term: serving applies no type default, so a bare default does not satisfy; the default reaches storage via `Mutation` scaffold/replace seeding and the write-boundary `Layout/LayoutDefaultSeeder`, not at diagnosis. `PropertyResolution::default` stays populated (the editor still sees the type default) but is not consulted for satisfaction.
- `unfilledRequiredInputViolations` runs per resolution alongside `propertyBindingViolation` inside `bindingViolations`, and fires only on a required Reference resolved via `CandidateOrigin::Stored`. It encodes the stored `DataRequirement` config (`DataLoaderConfigSerializerProvider::encode`) and reads the source's config specification (`AbstractContentSystemDataLoaderMapResolver::resolve()->configSpecificationFor`), emitting one `UnfilledRequiredInput` per **required** `propertyReference` config key whose configured (string) value names an element property with no stored value (`getProperty(...) === null`, stored explicit null included). Keyed on the input property when it is a declared primitive, else on the reference property (keying fallback for undeclared/non-primitive configured names). No loader source name appears — the rule keys off `ConfigKeyKind::PropertyReference` only. `encode()` is not wrapped in a client-defect catch: a Stored resolution proves the loader and (via the prior decode that built the config object) its serializer are registered, so a throw here is an internal fault that must surface.
- `RootContextMapper::resolveType()` throws `ContentSystemException` for unregistered source or unknown entity; `LayoutDiagnostics` catches client-defect codes and converts them to `InvalidConfig` violations.
- `storedRequirementViolation()` runs one `resolveType()` call per stored `DataRequirement` and reports exactly one outcome: a client-defect resolve failure is `InvalidConfig`; a resolve that succeeds but produces a type not assignable to the property's declared reference FQCN is `MismatchedReferenceType`; a resolve that succeeds and fits produces no violation (`Resolution/ElementResolver` reports it as a `Stored` resolution instead, never a `candidates` menu entry).
- `LayoutDiagnostics` has `@internal` on its constructor, not on the class itself.
