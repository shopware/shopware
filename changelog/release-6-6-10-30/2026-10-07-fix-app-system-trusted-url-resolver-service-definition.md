---
title: Fix app system trusted URL resolver service definition
issue: 21321
---
# Core
* Changed the service definition `shopware.app_system.trusted_url_resolver` to pass `null` instead of an empty string as the DNS resolver of `Shopware\Core\Content\Media\File\TrustedUrlResolver`. The empty string caused a `TypeError` when the service was created and made `bin/console lint:container` fail.
