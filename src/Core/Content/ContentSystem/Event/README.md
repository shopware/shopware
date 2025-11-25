# Event

Lifecycle events dispatched during content hydration.

## Key Classes

- `PreContentHydrationEvent` - Dispatched before hydration; allows layout preparation
- `AfterContentHydrationEvent` - Dispatched after hydration; allows layout finalization

## Event Structure

Both events share identical structure:

| Property | Type | Mutability |
|----------|------|------------|
| `elements` | `array<ContentElement>` | Mutable |
| `layoutId` | `string` | Readonly |
| `layoutName` | `string` | Readonly |
| `layoutVersionId` | `?string` | Readonly |
| `specification` | `RenderingSpecification` | Readonly |
| `salesChannelContext` | `SalesChannelContext` | Readonly |

The `elements` array is the transformation target; subscribers modify this array to prepare or finalize the layout tree.

## Lifecycle

The events form a pre/post pair around hydration:

```
PreContentHydrationEvent → Hydration → AfterContentHydrationEvent
```
