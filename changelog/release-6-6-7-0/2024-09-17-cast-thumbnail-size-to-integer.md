---
title: Cast thumbnail size to integer
issue: NEXT-38406
author: Vladislav Sultanov
author_email: vladislav.sultanov@netlogix.de
author_github: @TheBreaken
---
# Core
* Deprecated thumbnail size type to be native integer to prevent the error "Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity::getWidth(): Return value must be of type int, string returned" in 6.7.0.0
