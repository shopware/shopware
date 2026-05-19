---
title: Fix message deserialization
author: Christian Schiffler
author_email: c.schiffler@cyberspectrum.de
author_github: discordier
---
# Core
* Changed `Shopware\Core\Content\Media\Message\UpdateThumbnailsMessage` to have a proper setter that is understood by Symfony serializer.
