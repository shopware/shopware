---
title: Fix custom fields media in the Media page not usable
issue: NEXT-24043
---
# Administration
* Added a div element to wrapper the block `sw_media_field_action_bar` in `
  src/Administration/Resources/app/administration/src/app/asyncComponent/media/sw-media-field/sw-media-field.html.twig` to prevent event propagation when clicking inside media popover content. 
