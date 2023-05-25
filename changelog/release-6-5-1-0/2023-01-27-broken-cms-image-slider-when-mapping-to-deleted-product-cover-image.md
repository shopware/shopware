---
title: Broken CMS image slider when mapping to deleted product cover image
issue: NEXT-24850
author: Niklas Limberg
author_email: n.limberg@shopware.com
author_github: NiklasLimberg
---
# Core
* Changed `\Shopware\Core\Content\Media\Cms\Type\ImageSliderTypeDataResolver::enrich()` to check for the cover image entity, instead of just checking for the assignment.
