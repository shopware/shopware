@README.md

## Source Code References

- `LayoutDiagnostics` - Entry point. `analyze(array $tree, ?array $rootContext, ?Context $context = null): LayoutAnalysis`. Flattens the element tree, runs the duplicate-id check once as a cross-element batch pass over the flattened set, then the per-element intrinsic checks (unregistered component, invalid config, orphaned provider) on every element unconditionally, then binding checks only when `$rootContext !== null`.
- `LayoutAnalysis` - Output of `analyze()`. `public DiagnosticsReport $report` and `public array $resolutions` (keyed by element id, values are `list<PropertyResolution>`). `@internal final readonly`.
- `DiagnosticsReport` - Holds `list<Violation> $violations`. `isWellFormed(): bool` (no intrinsic-scope Error violations — persistence gate). `isResolvable(): bool` (no binding-scope Error violations — serving gate). Also `intrinsicErrors()`, `bindingErrors()`. `@internal final readonly`.
- `Violation` - `@internal final readonly`. Constructor: `ViolationCode $code, string $elementId, ?string $key, string $message, list<ResolutionCandidate> $candidates = []`. `scope()` and `severity()` delegate to the code.
- `ViolationCode` - `enum: string`, 8 cases. Single source of truth for scope and severity. See README for the full mapping table.
- `ViolationScope` - `enum: string`. Cases: `Intrinsic`, `Binding`. `@internal`.
- `ViolationSeverity` - `enum: string`. Cases: `Error`, `Warning`. `@internal`.
- `RootContextMapper` - `map(array<DataRequirement> $requirements): list<ProvidedContext>` maps a bound source's page data requirements to the root-ambient context for `analyze()` (each context broadcast `Single` from the virtual root). Also exposes `resolveType(DataRequirement): string`, used by the diagnostics core to detect `InvalidConfig`.

## Constraints

- `LayoutAnalysis` constructor order: `report` first, then `resolutions`.
- `analyze()` third argument `$context` is nullable with a `null` default; callers that only need well-formedness checks may omit it entirely.
- Binding checks are skipped when `$rootContext` is `null`; only the intrinsic subset runs.
- `ViolationCode` is the only place scope and severity are defined — do not derive them anywhere else.
- `RootContextMapper::resolveType()` throws `ContentSystemException` for unregistered source or unknown entity; `LayoutDiagnostics` catches client-defect codes and converts them to `InvalidConfig` violations.
- `LayoutDiagnostics` has `@internal` on its constructor, not on the class itself.
