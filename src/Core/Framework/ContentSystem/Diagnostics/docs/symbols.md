# Diagnostics Symbols

The symbol index for the diagnostics pass: each class, its role, and its path. The constraints that
govern editing them stay in [../AGENTS.md](../AGENTS.md), and the conceptual model in
[../README.md](../README.md).

- `LayoutDiagnostics` - Entry point. `analyze(array $tree, ?array $rootContext): LayoutAnalysis`. Flattens the element tree, reads the style option registry's strict `all()` and the existing `language` id set once each for the whole analysis, runs the duplicate-id check once as a cross-element batch pass over the flattened set, then the per-element intrinsic checks (unregistered component, invalid config, mismatched reference type on stored wiring, mismatched property type on a stored value, unknown style option, orphaned provider, dangling language) on every element unconditionally, then binding checks only when `$rootContext !== null`. Its constructor takes a `Doctrine\DBAL\Connection` for that language read.
- `LayoutAnalysis` - Output of `analyze()`. `public DiagnosticsReport $report` and `public array $resolutions` (keyed by element id, values are `list<PropertyResolution>`). `@internal final readonly`.
- `DiagnosticsReport` - Holds `list<Violation> $violations`. `isWellFormed(): bool` (no intrinsic-scope Error violations — persistence gate). `isResolvable(): bool` (no binding-scope Error violations — serving gate). Also `intrinsicErrors()`, `bindingErrors()`. `@internal final readonly`.
- `Violation` - `@internal final readonly`. Constructor: `ViolationCode $code, string $elementId, ?string $key, string $message, list<ResolutionCandidate> $candidates = []`. `scope()` and `severity()` delegate to the code.
- `ViolationCode` - `enum: string`. Single source of truth for scope and severity. See [../README.md](../README.md) for the full mapping table.
- `ViolationScope` - `enum: string`. Cases: `Intrinsic`, `Binding`. `@internal`.
- `ViolationSeverity` - `enum: string`. Cases: `Error`, `Warning`. `@internal`.
- `RootContextMapper` - `map(array<DataRequirement> $requirements): list<ProvidedContext>` maps a bound source's page data requirements to the root-ambient context for `analyze()` (each entry broadcast `Single`, marked `root: true`, and carrying a null `providerElementId`, because root context is ambient rather than provided from an element address). Also exposes `resolveType(DataRequirement): string`, used by the diagnostics core to detect `InvalidConfig`.
