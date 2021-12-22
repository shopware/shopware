---
title: deprecate changelog
issue: NEXT-19161
author: Jan Pietrzyk
author_email: j.pietrzyk@shopware.com
author_github: JanPietrzyk
---
# Core
* Deprecated `Shopware\Core\Framework\Changelog`, will be `@internal` 
___
# Upgrade Information

The current UPGRADE.md will from now on only contain extended information on non breaking additions. All breaking changes will be explained in the `UPGRADE.md` for the next major version release. At the time of writing this will be the `UPGRADE-6.5.md`.
______
# Next Major Version Changes

The whole namespace `Shopware\Core\Framework\Changelog` was marked `@internal` and is no longer part of the BC-Promise. Please move to a different changelog generator vendor.
