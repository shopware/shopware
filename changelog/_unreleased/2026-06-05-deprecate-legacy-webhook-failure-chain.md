---
title: Deprecate the legacy webhook failure chain ahead of the endpoint-health rework
author: Ghaith Olabi
author_email: m.olabi@shopware.com
author_github: @Gaitholabi
---
# Core
* Deprecated the pre-rework webhook failure chain for removal in v6.8.0 (with the `WEBHOOKS_REWORK` flag): `WebhookEntity::$active`/`$errorCount` and their accessors, `RelatedWebhooks::updateRelated()`, `RetryWebhookMessageFailedSubscriber::failed()`, and `WebhookHealthService::recordLegacyFailure()`/`resetErrorCount()`.
___
# Upgrade Information
## `webhook.active` and `webhook.error_count` are deprecated
These two columns are a legacy backwards-compat mirror of the per-endpoint health state and will be removed in v6.8.0 together with the `WEBHOOKS_REWORK` flag. Read the precise health from `GET /api/app-system/webhook/state` — `endpointState` for the tier (`healthy`/`degraded`/`suspended`/`disabled`); the mirrored `errorCount` reports the dominant failure streak until the removal. The staged removal sequence (drop the flag, delete the legacy chain, drop the columns, with byte-equivalence checkpoints) lives in `adr/2026-06-05-webhook-rework-v6.8.0-removal-runbook.md`.
