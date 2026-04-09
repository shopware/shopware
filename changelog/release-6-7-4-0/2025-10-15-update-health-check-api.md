---
title: Update health check API
author: Malte Janz
author_email: m.janz@shopware.com
author_github: @MalteJanz
---
# Core
* Added `\Shopware\Administration\Framework\SystemCheck\AdministrationReadinessCheck` that is part of the `pre_rollout` context
  to validate the administration is build correctly and healthy.
* Added optional `shopware.api.static_token.health_check` config option to `shopware.yaml`,
  that is best set as environment variable (because it should be secret).
  It's optionally used for the `/api/_info/system-health-check` API endpoint in addition to normal `Bearer` tokens
___
# API
* Changed `/api/_info/system-health-check` endpoint to not accept a `verbose` query param anymore. 
  It now always returns `extra` data if the health check has run and returned this data.
* Changed `/api/_info/system-health-check` to also accept a `Static` token as `Authorization` header, 
  as well as the already working `Bearer` token validation.
  The accepted static token can be set as `shopware.api.static_token.health_check` in `shopware.yaml`
