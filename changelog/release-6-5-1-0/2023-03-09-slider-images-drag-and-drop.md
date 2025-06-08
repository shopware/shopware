---
title: Slider Images Drag & Drop
issue: NEXT-10244
---
# Administration
* Added `moveItem` method in `/core/service/util.service.ts` to sort when drag and drop item.
* Changed in `app/asyncComponent/media/sw-media-list-selection-v2/index.js`
  * Changed `mediaItems` computed to add position index to image.
  * Added `onMediaItemDragSort` method to handle drag and drop item.
* Added `v-draggable` and `v-droppable` properties in `/app/asyncComponent/media/sw-media-list-selection-v2/sw-media-list-selection-v2.html.twig` to handle drag and drop item.
* Added `item-sort` event in `module/sw-cms/elements/image-gallery/config/sw-cms-el-config-image-gallery.html.twig` to handle drag and drop item event of Image gallery.
* Added `onItemSort` method in `/module/sw-cms/elements/image-gallery/config/index.js` to handle drag and drop item of Image gallery.
* Added `item-sort` event in `module/sw-cms/elements/image-slider/config/sw-cms-el-config-image-slider.html.twig` to handle drag and drop item event of Image Slider.
* Added `onItemSort` method in `/module/sw-cms/elements/image-slider/config/index.js` to handle drag and drop item of Image Slider.
* Changed `scss` in `/app/asyncComponent/media/sw-media-list-selection-item-v2/sw-media-list-selection-item-v2.scss` to keep the drag item ratio.
