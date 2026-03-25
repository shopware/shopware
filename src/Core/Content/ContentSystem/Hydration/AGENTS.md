# Hydration

@README.md

## Source Code References

- **Main class**: `ContentElementHydrator`
- **Data loaders**: See `DataLoader/AGENTS.md`
- **Context resolution**: See `DataContext/AGENTS.md`

## Critical Ordering Requirement

### Two-Phase Process (MUST RUN IN ORDER)

**Phase 1: Data Loading** MUST complete before **Phase 2: Context Resolution**

See `ContentElementHydrator::hydrate()` for implementation.

**Why this order matters**: Context providers may expose loaded data as context. If context resolution runs first, providers have no data to expose.

### Data Loading Process

Depth-first recursive traversal. For each element:
1. Load data per DataRequirement (using DataLoaderProvider)
2. Store result in element property by requirement key
3. Recurse to children

See `ContentElementHydrator::hydrateElement()` for implementation.

### DataRequirement → Property Mapping

Requirement key determines where result is stored. See `DataRequirement` class for structure. After hydration, data is accessible via `$element->getProperty($key)`.

## Process Flow

Two-phase pipeline: Data loading (recursive, depth-first) then context resolution (separate pass).

See `ContentElementHydrator::hydrate()` and `hydrateElement()` for implementation.

## Quick Reference

- **Phase 1**: Recursive depth-first data loading
- **Phase 2**: Context resolution (separate pass)
- **Requirement key**: Determines property storage location
- **Source identifier**: Selects which loader to use (see `DataLoader/AGENTS.md`)
- **Common mistake**: Inverting phase order or assuming key = entity type
- **Performance**: Minimize requirements, use associations for batch loading
