---
title: Add foreign key constraint to theme table
issue: NEXT-11876
---
# Storefront
* Added foreign key constraint for `theme`.`preview_media_id`
* Changed behaviour of `theme:refresh` to not remove theme preview media if it is not created by this theme
* Changed behaviour of `theme:refresh` to not replace theme preview media if set explicitly by the user
