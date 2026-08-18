# Using Product Analytics in Services

Extensions and apps run inside the Admin iframe and communicate with it via the
[Meteor Admin SDK](https://github.com/shopware/meteor). Instead of calling `Shopware.Telemetry.track()`
directly (not available in the iframe context), use the SDK's `telemetry` module – events are forwarded to
the Admin over `postMessage` and routed through the same pipeline as Admin core events.

## Key differences from Admin core

| | Admin core | Extension / Service                                                        |
|---|---|----------------------------------------------------------------------------|
| **Method** | `Shopware.Telemetry.track(eventData)` | `telemetry.dispatch({ event, data })` from `@shopware-ag/meteor-admin-sdk` |
| **Transport** | Direct (same JS context) | `postMessage` via Admin SDK -> Admin receives and forwards                 |
| **Auto-tracking** | DOM observer, `data-analytics-id` attributes | Not available – all tracking is explicit                                   |
| **Consent gating** | Handled internally by Admin | Handled by Admin on receipt – no action needed in the service              |
| **Debug mode** | `Shopware.Telemetry.debug = true` in browser console | Not available – inspect `postMessage` traffic in DevTools instead          |

## Firing events

```ts
import { telemetry } from '@shopware-ag/meteor-admin-sdk';

await telemetry.dispatch({
    event: 'my_extension_something_happened',
    data: {
        my_extension_some_property: 'value',
        my_extension_count: 42,
    },
});
```

The SDK also ships pre-built helpers for common cases:

```ts
// page navigation
await telemetry.trackPageView({
    sw_route_from_href: previousUrl,
    sw_route_from_name: previousRouteName,
    sw_route_to_href: currentUrl,
    sw_route_to_name: currentRouteName,
});

// outbound or internal link clicks
await telemetry.trackLinkVisited({
    sw_link_href: href,
    sw_link_type: 'external',
});
```

The source extension name is resolved automatically from the iframe origin – it cannot be set manually.

A minimal composable wrapper keeps call sites clean (pattern from SwagAnalytics
`client/src/composables/use-telemetry/index.ts`):

```ts
import { telemetry } from '@shopware-ag/meteor-admin-sdk';

type TrackableValue = string | string[] | number | boolean | null;

export function useTelemetry() {
    async function track(event: string, data?: Record<string, TrackableValue>) {
        await telemetry.dispatch({ event, data: data as Record<string, unknown> | undefined });
    }

    return { track };
}
```

Usage in a component (SwagAnalytics `client/src/components/date-filter/date-filter.vue`):

```ts
const { track } = useTelemetry();

void track('analytics_date_filter_applied', {
    analytics_date_range: dateRange,
    analytics_range_start: format(from, 'yyyy-MM-dd'),
    analytics_range_end: format(to, 'yyyy-MM-dd'),
});
```

## Naming conventions

Follow the same conventions as Admin core (see [Readme.md](./Readme.md)), with one addition: **prefix all
event names and property keys with your extension identifier** to avoid collisions.

### Event names

Pattern: `<extension>_<object>_<action>` in `snake_case`

```
analytics_viewed
analytics_export_triggered
analytics_date_filter_applied
analytics_storefront_tracking_toggled
```

### Property keys

Pattern: `<extension>_<property>` in `snake_case`

```
analytics_entry_point
analytics_export_format
analytics_date_range
analytics_range_start
analytics_range_end
analytics_sales_channel_id
```

### Property values

Scalar only: `string | string[] | number | boolean | null`. No objects or nested arrays.

## Consent

You do not need to check consent before calling `telemetry.dispatch()`. The Admin will not forward events to
the tracking client if the shop operator has not accepted product analytics – your service gets this
automatically.

If your service needs to react to consent state (e.g. to show or hide a UI element, or to gate your own
behavior), use the SDK `consent` module:

```ts
import { consent } from '@shopware-ag/meteor-admin-sdk';

// read current state
const state = await consent.status({ consent: 'product_analytics' });

if (state.isAccepted && !state.isStale) {
    // accepted and up to date with the current revision
}

if (state.isAccepted && state.isStale) {
    // previously accepted but a newer revision exists – treat as not accepted
}
```

To prompt the operator to accept a consent (your own or an existing one):

```ts
const { requestPromise, abort } = consent.request({
    consent: 'my_extension_consent',
    requestMessage: 'We need your consent to enable this feature.',
    privacyLink: 'https://example.com/privacy',
});

const result = await requestPromise;
```

The `Consent` object returned by both methods has the same shape:

```ts
{
    name: string;
    status: 'unset' | 'declined' | 'revoked' | 'accepted';
    updatedAt: string | null;
    acceptedRevision: string | null;
    lastRevision: string | null;
    isAccepted: boolean;  // status === 'accepted', not revision-aware
    isStale: boolean;     // accepted for an older revision than the current one
}
```

## Known limitations

- The DOM observer and `data-analytics-id` attribute approach from Admin core are not available in the iframe
  context. Every event must be dispatched explicitly.
- `Shopware.Telemetry.debug = true` has no effect from inside an iframe. Use the browser's `postMessage`
  event listener in DevTools to inspect dispatched events.
- The source extension name is resolved from the iframe origin automatically and cannot be overridden – keep
  this in mind when filtering events in analytics tooling.
- Using `void track(...)` swallows errors silently. Await the call if you need to handle failures.
