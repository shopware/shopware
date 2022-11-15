---
title: Deprecate app manifest version 1.0
issue: NEXT-24009
author: Sebastian Franze
author_email: s.franze@shopware.com
author_github: Sebastian Franze
---
# Core
* Deprecated `src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd`. Will be removed in 6.5.0.0
* Deprecated attribute `openNewTab` for element `action-button` in `src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd`. Use ActionButton responses instead.
* Added `src/Core/Framework/App/Manifest/Schema/manifest-2.0.xsd`
___
# Upgrade Information

## Deprecated manifest-1.0.xsd

With the upcoming major release we are going to release a new XML-schema for Shopware Apps. In the new schema we remove two deprecations from the existing schema.

1. attribute `parent` for element `module` will be required.

   Please make sure that every of your admin modules has this attribute set like described in [our documentation](https://developer.shopware.com/docs/guides/plugins/apps/administration/add-custom-modules)
2. attribute `openNewTab` for element `action-button` will be removed.

    Make sure to remove the attribute `openNewTab` from your `action-button` elements in your `manifest.xml` and use ActionButtonResponses as described in our [documentation](https://developer.shopware.com/docs/guides/plugins/apps/administration/add-custom-action-button) instead.
3. Deprecation of `manifest-1.0.xsd`

    Update the `xsi:noNamespaceSchemaLocation` attribute of your `manifest` root element. to `https://raw.githubusercontent.com/shopware/platform/trunk/src/Core/Framework/App/Manifest/Schema/manifest-1.0.xsd`
