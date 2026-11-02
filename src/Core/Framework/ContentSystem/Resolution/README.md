# Resolution

Property-resolution kernel for the ContentSystem. Given an element's position in a layout tree, determines how each declared type property can be filled: primitives carry a static value; reference properties collect candidate sources (ancestor providers, the layout's root-ambient context, and data loaders) with a deterministic conservative default selection.

## Key Classes

Two kernels do the work: `ElementResolver` resolves one element's declared properties at a position; `AvailableContextResolver` computes which context reaches that position with one formula for every depth: what the ancestor path exposes, simulating runtime redistribution, plus the layout's root-ambient set appended verbatim. Both are consumed by `Diagnostics/LayoutDiagnostics` via constructor injection. See [AGENTS.md](AGENTS.md) for the full symbol index with signatures.

Mapped reference properties back providers through the shared mapping inspector, using the explicitly supplied root source. An admitted candidate supplies its effective output type; the stored mapping's cardinality is checked during admission. The provider's existing delivery alias, distribution, and context type stay intact. A root media mapping therefore exposes media rather than the surrounding product, and a plugin mapping needs no ambient entry with a matching name.

This is static capability checking: no source values are fetched and no mapping reader runs. A rejected or unbound mapping cannot back a provider through a loader or an unrelated ambient type. Ordinary wiring keeps its existing resolution path.
