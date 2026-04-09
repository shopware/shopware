---
title: Add custom field entities (unit and newsletter_recipient) to apps
issue: #9635
---
# Core
* Changed `manifest.xsd` to allow defining custom field sets for all entities that support to show custom fields in the admin, specifically the `unit` and `newsletter_recipient` entities were added.
___ 
# Upgrade Information
## App System: Unit and NewsletterRecipient support for custom field sets

It is now possible to define custom field sets in your app's `manifest.xml` file for the `unit` and `newsletter_recipient` entities.