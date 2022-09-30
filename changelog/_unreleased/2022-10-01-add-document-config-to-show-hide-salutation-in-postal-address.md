---
title: Add document config to show/hide salutation in postal address
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Core
* Added `displaySalutationInPostalAddress` to `\Shopware\Core\Checkout\Document\DocumentConfiguration::$displaySalutationInPostalAddress`
* Added migration `\Shopware\Core\Migration\V6_4\Migration1664582400AddDocumentConfigDisplayingSalutationInPostalAddress` to enable `displaySalutationInPostalAddress` in every document configuration
* Added check to `config.displaySalutationInPostalAddress` in `documents/includes/letter_header.html.twig` to display salutation in postal address by configuration
___
# Administration
* Added checkbox with translation in `sw-settings-document.detail.labelDisplaySalutationInPostalAddress` to `sw-settings-document-detail` to edit `displaySalutationInPostalAddress`
