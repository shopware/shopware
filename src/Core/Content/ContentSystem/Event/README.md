# Event

Lifecycle events dispatched during content hydration.

## Key Classes

- `PreContentHydrationEvent` - Dispatched before hydration; allows layout preparation
- `PostHydrationEvent` - Dispatched after hydration; allows layout finalization

## Event Structure

Both events share identical structure:

| Property | Type | Mutability |
|----------|------|------------|
| `elements` | `array<ContentElement>` | Mutable |
| `layoutId` | `string` | Readonly |
| `layoutName` | `string` | Readonly |
| `layoutVersionId` | `?string` | Readonly |
| `specification` | `RenderingSpecification` | Readonly |
| `mode` | `RenderingMode` | Readonly |
| `salesChannelContext` | `SalesChannelContext` | Readonly |
| `cacheContext` | `RenderingCacheContext` | Readonly |

The `elements` array is the transformation target; subscribers modify this array to prepare or finalize the layout tree. The `cacheContext` allows subscribers to add cache tags or disable caching if needed.

## Lifecycle

The events form a pre/post pair around hydration:

```
PreContentHydrationEvent → Hydration → PostHydrationEvent
```
