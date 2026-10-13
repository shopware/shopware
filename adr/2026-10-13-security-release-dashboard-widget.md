---
title: Security release dashboard widget with per-user acknowledgement
date: 2026-10-13
area: framework
tags: [security, update, dashboard, admin-api]
---

## Context

Merchants currently learn about a new Shopware release through the update wizard and a notification shown after login. There is no dedicated, persistent place that highlights security releases specifically and pushes the merchant to act. At the same time, the release endpoint does not distinguish security releases from regular releases; it returns a generic changelog payload.

## Decision

### Identify security releases from the existing changelog payload

The changelog JSON already carries a `fixedVulnerabilities` array with severity, summary, and link for every patched vulnerability. A new `SecurityReleaseDetector` service treats a release as a security release when this array is non-empty and derives the highest severity from the entries. If the array is empty, the service falls back to scanning the title and body for `security`, `CVE-`, or `GHSA-` keywords.

The detector returns a `SecurityRelease` value object that the dashboard widget and any future consumers can use without parsing changelog text themselves.

### Add dedicated endpoints for the widget

Two new routes are added to the existing update controller:

* `GET /api/_action/update/security-release` returns the latest security release, or an empty response when the installed version is current, no security release exists, or the current user has already acknowledged this version.
* `POST /api/_action/update/security-release/dismiss` stores a per-user acknowledgement so the widget is hidden for that user.

Both endpoints require the existing `system:core:update` privilege and run independently of the `shopware.auto_update.hide_module` flag, because a read-only security nudge remains useful even when the merchant manages updates through Shopware CLI.

### Store acknowledgement per user in `user_config`

The acknowledgement is persisted in the existing `user_config` table using the key `security_release.dismissed`. The value contains the dismissed version and a timestamp. Because the table is keyed by `user_id`, one user acknowledging the message does not hide it for other users. When a new security release appears, the stored version no longer matches and the widget is shown again.

### Render a persistent banner on the dashboard

A new `sw-dashboard-security-release` component is rendered at the top of the dashboard. It uses an `mt-banner` with a critical variant for high or critical severities. The banner shows the release version, the number of fixed vulnerabilities, and the highest severity. It offers an "Update now" action to the existing update wizard (when the user has the privilege and the update module is visible), a link to the release notes, and an "I understand – do not show again" action that calls the dismiss endpoint.

## Considered Approaches

### Add a new external endpoint that only returns security releases

Rejected because the existing `releases.shopware.com` changelog already contains `fixedVulnerabilities`. Adding a separate endpoint would duplicate data and require release-pipeline changes before the feature could ship.

### Store acknowledgements in `system_config`

Rejected because `system_config` is global. The requirement explicitly states that acknowledgement is per user, so `user_config` is the natural fit.

### Hide the widget when `shopware.auto_update.hide_module` is true

Rejected because hiding the module is a deployment choice about update mechanics, not about security awareness. A merchant using Shopware CLI to update can still benefit from a read-only nudge. The "Update now" button is hidden when the module is not registered.

## Consequences

### Positive

* Merchants see a persistent, hard-to-miss security nudge on the dashboard.
* Security releases are identified from structured data already produced by the release pipeline.
* User-level acknowledgement prevents alert fatigue while ensuring new releases surface again.
* The detection logic is centralized in `SecurityReleaseDetector`, making future changes (for example, an explicit `security` flag in the changelog) trivial.

### Negative / trade-offs

* The widget is only visible to users who hold the `system:core:update` privilege. Broader visibility would require a dedicated ACL privilege.
* Dismissing stores a row per user. The volume is negligible because security releases are infrequent.
* The fallback keyword scan is less precise than `fixedVulnerabilities`; it is only a safety net for older changelogs.
