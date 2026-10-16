---
title: Security release dashboard widget
date: 2026-10-13
area: framework
tags: [security, update, dashboard, admin-api]
---

## Context

Merchants currently learn about a new Shopware release through the update wizard and a notification shown after login.
There is no dedicated, persistent place that highlights security releases specifically and pushes the merchant to act.
At the same time, the release endpoint does not distinguish security releases from regular releases; it returns a generic changelog payload.

## Decision

### Identify security releases from a dedicated security release list

The release pipeline publishes `https://releases.shopware.com/changelog/security-release.json` with the same shape as `index.json`: a list of version strings that contain security fixes.
The backend fetches this list, picks the latest entry, and compares it against the installed version.
If the installed version is older, the corresponding changelog JSON is loaded to obtain severity and vulnerability details.

The endpoint builds a `SecurityRelease` value object from the changelog's `fixedVulnerabilities` array, deriving the highest severity so the widget does not need to parse changelog text itself.

### Add a dedicated endpoint for the widget

A new route is added to the existing update controller:

* `GET /api/_action/update/security-release` returns the latest pending security release, or an empty response when the installed version is current or no security release exists.

The endpoint is open to any authenticated Administration user and runs independently of the `shopware.auto_update.hide_module` flag, because a read-only security nudge remains useful even when the merchant manages updates through Shopware CLI.
The "Update now" action in the widget still requires the `system:core:update` privilege.

### Render a persistent banner on the dashboard

A new `sw-dashboard-security-release` component is rendered at the top of the dashboard.
It uses an `mt-banner` with a critical variant for high or critical severities.
The banner shows the release version, the number of fixed vulnerabilities, and the highest severity.
It offers an "Update now" action to the existing update wizard (when the user has the privilege and the update module is visible) and a link to the release notes.

The banner is not dismissible.
It disappears automatically once the shop is on a version that is no longer affected by the reported security release.

## Considered Approaches

### Detect security releases by scanning each changelog for `fixedVulnerabilities`

Rejected because the shop may be several releases behind.
Only checking the latest changelog would miss a security release that is no longer the most recent version.
Scanning every intermediate changelog would be slow and fragile.

A dedicated `security-release.json` list is simpler and authoritative: it explicitly enumerates every affected version, so the backend can always return the highest pending security release.

### Make the banner dismissible per user

Rejected.
A dismissible banner would require per-user state and could hide critical information from users who did not yet acknowledge it.
The chosen design keeps the banner visible as long as a security release is pending and removes it automatically once the shop is up to date.

### Hide the widget when `shopware.auto_update.hide_module` is true

Rejected because hiding the module is a deployment choice about update mechanics, not about security awareness.
A merchant using Shopware CLI to update can still benefit from a read-only nudge.
The "Update now" button is hidden when the module is not registered.

## Consequences

### Positive

* Merchants see a persistent, hard-to-miss security nudge on the dashboard.
* Security releases are identified from structured data already produced by the release pipeline.
* The banner disappears automatically once the shop is patched, so there is no need for manual acknowledgement or per-user state.
* The `SecurityRelease` value object is built directly from the changelog, keeping the endpoint self-contained.

### Negative / trade-offs

* The widget is visible to every Administration user, but the "Update now" button is guarded by the `system:core:update` privilege.
* Merchants cannot dismiss the banner manually; it stays visible until the shop is updated.
* The banner depends on `security-release.json` being kept up to date in the release pipeline.
