# 6.6.10.32 (upcoming)

## Security Fixes

### API association compatibility

Admin API and Store API requests, and app scripts using `services.repository` or `services.store`, ignore requested associations that are not visible in the caller's API scope, including their nested criteria.
Selections in `fields` that traverse a non-visible association are also ignored.
Valid selections and associations or fields added by server code are preserved.
If no field selections remain, the normal read behavior applies, with API response visibility still enforced.
These selections are ignored silently, including in debug mode.
Starting with 6.8, these requests fail with HTTP 400 and `FRAMEWORK__ACCESSOR_NOT_ALLOWED`; remove non-visible associations and field selections that traverse them before upgrading.
Filters, sorting and aggregations retain their existing validation.
App permissions do not grant additional association visibility through `services.store`.
