---
title: Fix Google Consent Mode v2 default and update implementation
issue: 4307
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed Google Consent Mode v2 implementation to properly use `default` command with only denied states and `update` command for granted states (see: https://developers.google.com/tag-platform/security/guides/consent?hl=en&consentmode=advanced#default-consent)
* Changed the consent initialization to always set all consent types to `denied` by default
* Added separate `update` call that only grants consents based on cookie preferences in `src/Storefront/Resources/views/storefront/component/analytics.html.twig`
