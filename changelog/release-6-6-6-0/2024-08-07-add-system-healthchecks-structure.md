---
title: Add system healthchecks structure
issue: NEXT-37362
---

# Core
* Added the System checks structure under `Shopware\Core\Framework\SystemCheck\` to the core. For more details: [ADR](../../adr/2024-08-02-system-health-check.md)
* Added `system:check` CLI command to run all system checks
* Added `/api/_info/system-health-check` endpoint to get the system health status
