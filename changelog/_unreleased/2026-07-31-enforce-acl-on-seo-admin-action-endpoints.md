---
title: SEO admin action endpoints now require ACL privileges
issue: #18813
---
# API
* Changed four admin action endpoints that previously only required authentication to enforce ACL privileges. Requests with tokens lacking the privilege receive a `403` with `FRAMEWORK__MISSING_PRIVILEGE_ERROR`:
    * `PATCH /api/_action/seo-url/canonical` requires `seo_url:update`.
    * `POST /api/_action/seo-url/create-custom-url` requires `seo_url:create`.
    * `POST /api/_action/seo-url-template/context` and `GET /api/_action/seo-url-template/default/{routeName}` require `seo_url_template:read`.
* Added `seo_url:update` to the "Products editor", "Categories editor", and "Landing pages editor" permissions in the role editor — these are the roles whose users write canonical URLs when saving — and a migration grants it to existing roles that already hold one of those permissions, so Administration users are not affected. The template privileges are already part of the system configuration permission that the SEO settings page requires. Integrations and API clients with manually assigned privilege lists must add the respective privilege to their ACL role.
