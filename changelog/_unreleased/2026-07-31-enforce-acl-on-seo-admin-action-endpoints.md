---
title: Enforce ACL on SEO admin action endpoints
issue: #18813
---
# API
* Changed the SEO admin action endpoints to require ACL privileges: `PATCH /api/_action/seo-url/canonical` requires `seo_url:update`, `POST /api/_action/seo-url/create-custom-url` requires `seo_url:create`, and `POST /api/_action/seo-url-template/context` as well as `GET /api/_action/seo-url-template/default/{routeName}` require `seo_url_template:read`. Admin API integrations and users calling these endpoints must be granted the respective privilege.
___
# Core
* Added `Migration1785334458AddSeoUrlUpdateAclPrivilege`, which grants `seo_url:update` to existing ACL roles containing the `product.editor`, `category.editor`, or `landing_page.editor` privilege, so existing admin roles keep access.
___
# Administration
* Changed the `product.editor`, `category.editor` and `landing_page.editor` privilege mappings to include `seo_url:update`.
