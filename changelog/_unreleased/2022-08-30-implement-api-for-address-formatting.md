---
title: Implement api for address formatting
issue: NEXT-22180
---
# Core
* Added new structs `Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippet` and `Shopware\Core\Framework\Struct\CustomSnippet\CustomSnippetCollection` to define custom snippets
* Added a column `address_format (JSON)` in `country_translation` table
* Added default twig templates in `src/Core/Framework/Resources/views/snippets`
* Added a new template `src/Core/Framework/Resources/views/snippets/render.html.twig` to render custom snippets
* Changed the template `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig` and `src/Core/Framework/Resources/views/documents/delivery_note.html.twig` to render country address format using new structure
* Added a new controller `Shopware\Core\Framework\Api\Controller\CustomSnippetFormatController`
___
# Storefront
* Changed the template `src/Storefront/Resources/views/storefront/component/address/address.html.twig` to render country address format using new structure
___
# Upgrade Information
## Define country address formatting structure
From the next major v6.5.0.0, address of a country are no longer fixed, but you can modify it by drag-drop address elements in admin Settings > Countries > detail page > Address tab
The address elements are stored as a structured json in `country_translation.address_format`, the default structure can be found in `\Shopware\Core\System\Country\CountryDefinition::DEFAULT_ADDRESS_FORMAT`
## Extension can add custom element to use in address formatting structure
* Plugins can define their own custom snippets by placed twig files in `<pluginRoot>/src/Resources/views/snippets`, you can refer to the default Core address snippets in `src/Core/Framework/Resources/views/snippets/address`
___
# Next Major Version Changes
## Remove static address formatting:
* Deprecated fixed address formatting, use `@Framework/snippets/render.html.twig` instead, applied on:
  - `src/Storefront/Resources/views/storefront/component/address/address.html.twig`
  - `src/Core/Framework/Resources/views/documents/delivery_note.html.twig`
  - `src/Core/Framework/Resources/views/documents/includes/letter_header.html.twig`

