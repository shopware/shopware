# Resolution

Property-resolution kernel for the ContentSystem. Given an element's position in a layout tree, determines how each declared type property can be filled: primitives carry a static value; reference properties collect candidate sources (ancestor/root context providers and data loaders) with a deterministic conservative default selection.

## Key Classes

Two kernels do the work: `ElementResolver` resolves one element's declared properties at a position; `AvailableContextResolver` computes which context reaches that position by simulating runtime redistribution along the ancestor path. Both are consumed by `Diagnostics/LayoutDiagnostics` via constructor injection. See [AGENTS.md](AGENTS.md) for the full symbol index with signatures.
