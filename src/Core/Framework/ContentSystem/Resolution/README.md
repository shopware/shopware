# Resolution

Property-resolution kernel for the ContentSystem. Given an element's position in a layout tree, determines how each declared type property can be filled: primitives carry a static value; reference properties collect candidate sources (ancestor providers, the layout's root-ambient context, and data loaders) with a deterministic conservative default selection.

## Key Classes

Two kernels do the work: `ElementResolver` resolves one element's declared properties at a position; `AvailableContextResolver` computes which context reaches that position with one formula for every depth: what the ancestor path exposes, simulating runtime redistribution, plus the layout's root-ambient set appended verbatim. Both are consumed by `Diagnostics/LayoutDiagnostics` via constructor injection. See [AGENTS.md](AGENTS.md) for the full symbol index with signatures.
