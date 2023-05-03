---
title: Fix permissions for creating documents in sw-order
issue: NEXT-24960
author: Markus Velt
author_email: m.velt@shopware.com
author_github: @raknison
---
# Administration
* Changed acl permissions from `order.editor` to `document.viewer` in `sw-order-document-card`
* Added acl permission `media_default_folder:read` for role `order.editor` 
