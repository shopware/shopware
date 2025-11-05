# 6.7.5.0 (upcoming)

## Features

## API

## Core

## Administration

## Storefront

## App System

## Hosting & Configuration

## Critical fixes


# 6.7.4.0

## Improvements

# 6.7.3.0

## Improvements

### Language handling

#### American English can be used in installer
American English can now be downloaded in the installer and can become the default shop language like any other language in Shopware.

#### Available languages can be managed from Shopware core
No plugin needed anymore to install languages available from the Shopware translation platform! The entire plugin has been built into the core. Simply fetch and activate the language of your choice via the new bin/console commands. Later, this feature will become available in administration.

However, for any other language pack not available from the Shopware translation platform, you will still need a plugin.

You can fetch Shopware translations from the Shopware translation platform, which are stored on Github. You can even help provide translations and use them in your shop a short time later!

Please note: As these are community-provided translations, we cannot guarantee that everything is translated 100% correctly.

Good news: The Language Pack plugin will continue to be maintained under our usual release policy.

Please see the ADR for more details.

#### Country-Agnostic Language Layer

Working with language codes in Shopware, such as en-GB (a combination of language and country), generally works well. However, this approach can be quite maintenance-heavy: using multiple dialects, for example, British and American English, always leads to duplicated language snippets and can quickly become frustrating for translators.

To address this, we introduced an additional translation layer that reduces dialects to patch files, limiting duplication to only a small portion of the snippets.

Read the full story in this ADR. You can also find a detailed concept document for further reference.

### CMS / Shopping Experience

#### Block type labels
See the type of blocks directly when working with it as an editor. This is especially useful if using third party plugins. Thanks to @amenk!

https://github.com/shopware/shopware/pull/12334

#### 3D/canvas switching
Slider viewers are now rendered in respect to their visibility modus. This gives us a bit of more performance. Thanks to @ffrank913 😉

https://github.com/shopware/shopware/pull/12642

#### Performance: Faster product category loading with a new index
Thanks to this pull request, queries on product.categories shall run ways faster than before: See https://github.com/shopware/shopware/pull/12657 by @vienthuong

#### Checkout & Promotions: More reliable shipping price matrix, credit notes, and promotion discount calculations
https://github.com/shopware/shopware/pull/12560 by @untilu29 actually fixes Shipping method cannot be applied to products below 1 EUR due to “Cart price from” default by @cramytech.
https://github.com/shopware/shopware/pull/12589 by @ennasus4sun fixes Credit notes are created cumulatively by @swagTKA.
https://github.com/shopware/shopware/pull/12603 by @socrec fixes Fixed Price delivery promotions cannot be excluded by janobi

#### 3D Viewer: Improved visuals with better camera distance and model placement
https://github.com/shopware/shopware/pull/12682 by ffrank913 fixes Incorrect model focus in SW6 standard CMS by himself
https://github.com/shopware/shopware/pull/12654 by ffrank913 fixes Incorrect frontend display of 3D glb files in SW6 standard CMS by MaximilianFo

### More tech updates
Framework & API: Store-API cookie groups, new route exception handling, cleaner query parsing
Platform ops / DX: Environment variable improvements, cache directory configurability, profiler disabled by default in production
Build tooling: Admin build target updated to ES2023 (plugin authors should check compatibility)
Deprecations / Breaking changes:
Removal of controllerName/controllerAction variables in templates
Deprecation of SalesChannelContextSwitcher
Upgrade notes: DB migration for the new category index, admin build target upgrade, profiler defaults