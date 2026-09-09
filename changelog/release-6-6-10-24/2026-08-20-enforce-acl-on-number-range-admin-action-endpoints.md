---
title: Number range admin action endpoints now require ACL privileges
issue: #19522
---
# API
* Changed two admin action endpoints that previously only required authentication to enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:
    * `GET /api/_action/number-range/reserve/{type}/{salesChannelId}` requires `number_range:read`. Without `preview=1` this endpoint permanently advances the number range state, so it was possible for any authenticated backend account to consume invoice, order, delivery-note and credit-note numbers and create gaps in the sequence.
    * `GET /api/_action/number-range/preview-pattern/{type}` requires `number_range:read`.
* Added `number_range:read` to the "Orders editor" and "Customers creator" permissions in the role editor — these are the roles whose users reserve document, order and customer numbers — and a migration grants it to existing roles that already hold one of those permissions, so Administration users are not affected. The "Products viewer" and "Number ranges viewer" permissions already carry it. Integrations and API clients with manually assigned privilege lists must add `number_range:read` to their ACL role.
