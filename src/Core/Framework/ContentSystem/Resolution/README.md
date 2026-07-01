# Resolution

Property-resolution kernel for the ContentSystem. Given an element's position in a layout tree, determines how each declared type property can be filled: primitives carry a static value; reference properties collect candidate sources (ancestor/root context providers and data loaders) with a deterministic conservative default selection.

## Key Classes

- `ElementResolver` - per-element kernel; `resolve(ContentElement|string, ResolutionContext): list<PropertyResolution>` — returns one `PropertyResolution` per declared type property
- `AvailableContextResolver` - context-walk kernel; `resolve(string $targetElementId, list<ContentElement> $tree, list<ProvidedContext> $rootContext): list<ProvidedContext>` — computes which context is available at a target element's position by simulating runtime redistribution along the ancestor path (not a union of all ancestor providers): each ancestor exposes only providers that resolve on it plus its `redistribute` re-broadcasts; section-agnostic
- `PropertyResolution` — resolution of one property: kind (`Primitive`/`Reference`), required flag, candidates, and the conservative default selection
- `ResolutionCandidate` — one candidate source for a Reference property: a `Parent` (ancestor/root provider), a `Loader` (data loader), or `Stored` (the element's own applied wiring), with config completeness flag
- `ProvidedContext` — a single context entry available at a position; six fields: `contextKey`, `fqcn`, `contextType` (`ContextType`, required), `providerElementId` (`?string`), `distribution` (`DistributionStrategy`), and `path` (`?string`, optional)
- `ResolutionContext` — input bundle passed to `ElementResolver`: target element id plus the `list<ProvidedContext>` from `AvailableContextResolver`
- `PropertyKind` — enum: `Primitive`, `Reference`
- `CandidateOrigin` — enum: `Parent`, `Loader`, `Stored` — where a candidate value originates. `Stored` marks the element's own applied wiring: a stored requirement whose produced type resolves and is assignable to the declared FQCN becomes the `resolved` pick directly, never a `candidates` menu entry
