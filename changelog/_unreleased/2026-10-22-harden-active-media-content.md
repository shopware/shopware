---
title: Restrict active media uploads and protect Storefront documents
issue: https://github.com/shopware/shopware-private/issues/513
---

# API

* Removed `html` from the default public media extension allowlist. Private HTML remains supported. Existing public HTML files remain accessible and should be reviewed.
* Changed media upload validation to reject HTML, XHTML, SVG or XML content types that do not match their file extension. Passive MIME mismatches remain accepted.

___

# Storefront

* Added an enforcing Content Security Policy to successful Storefront document file responses. Scripts, embedded documents, base URL changes and form submissions are blocked; images and styles remain permitted.

___

# Upgrade Information

## Active media uploads and Storefront documents

Public HTML uploads are rejected by default. Review existing public HTML assets and remove files that are no longer needed. Installations that explicitly allow public HTML must protect those assets at the serving layer, for example with a suitable Content Security Policy or a separate origin.

Custom HTML document templates must work without scripts, embedded documents, base URL changes or form submissions. The Storefront document policy can be overridden through the container parameter `shopware.security.private_media_csp` in `config/services.yaml`. Admin API and Store API responses retain their existing CSP.
