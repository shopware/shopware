# DataContext

@README.md

## Coding Patterns

### Stack-Based Scoping with Immediate Distribution

Context distribution uses two mechanisms: stack (for descendants) and immediate distribution (for direct children). See `ContextResolutionVisitor::enter()` and `distributeContextToChildren()` for implementation.

### Shadow Semantics

Inner providers shadow outer for same context key. Last pushed value wins - consumers receive from innermost provider. See `DataContextStack` for implementation.

### Distribution Strategies

Five strategies control how provider data distributes to direct children:

1. **Broadcast**: All children receive identical data (use for: shared data like product detail)
2. **Indexed**: Position-based, child[N] gets data[N] (use for: fixed-position layouts)
3. **Keyed**: Children receive by matching `data_key` property (use for: named sections like "featured", "sale")
4. **Sliced**: Data split into chunks across children (use for: column layouts)
5. **Iterator**: Round-robin distribution (use for: even distribution across slots)

See `DistributionStrategyInterface` implementations for details.

## Common Mistakes

### 1. Modifying Stack During Traversal

**Wrong**: Manual stack manipulation during resolution

**Right**: Use provider/consumer definitions, let resolver handle stack

## Quick Reference

- **Two mechanisms**: Stack (descendants) + Immediate distribution (direct children)
- **Shadow semantics**: Inner provider shadows outer for same context key
- **Strategy scope**: Only applies to direct children, deeper descendants use stack
- **Stack manipulation**: Never push/pop manually, use provider/consumer definitions
- **Strategies**: Broadcast (shared), Indexed (position), Keyed (named), Sliced (chunks), Iterator (round-robin)
- **Implementation**: See ContextResolutionVisitor for visitor pattern
- **Interface**: All strategies implement DistributionStrategyInterface
