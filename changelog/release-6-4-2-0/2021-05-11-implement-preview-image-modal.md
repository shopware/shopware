---
title: Implement preview image modal
issue: NEXT-14121
---
# Administration
* Changed in `src/app/component/media/sw-image-slider/index.js`
    * Added prop `itemPerPage` for number of images display in a page.
    * Added prop `initialIndex` to set start index for the slider.
    * Added prop `arrowStyle` to configure style of arrows with 2 options `inside` or `outside`.
    * Added prop `buttonStyle` to configure style of buttons with 2 options `inside` or `outside`.
    * Added prop `displayMode` to configure style of image with 2 options `cover` or `contain`.
    * Added prop `rewind` to configure rewind mode for slider.
    * Added prop `autoWidth` to configure `autoWidth` mode for each image.
    * Added prop `bordered` to configure `border` style for the image container.
    * Added prop `rounded` to configure `border-radius` style for the container.
    * Changed prop `canvasWidth` configuration: set `required` to false, add validator.
    * Changed prop `canvasHeight` configuration: add validator.
    * Changed prop `gap` configuration: set `required` to false, add validator.
    * Added computed property `totalPage` to get calculated total page.
    * Added computed property `remainder` to get remainder after dividing total items and itemPerPage.
    * Added computed property `buttonList` to get calculated number of buttons.
    * Added computed property `wrapperStyles` to set canvasWidth for the slider.
    * Added computed property `imageStyles` to set style for each image.
    * Added computed property `buttonClasses` to set button style for button container
    * Added computed property `showButtons` to show arrow navigation or not. 
    * Added computed property `showArrows` to show button navigation or not.
    * Changed computed property `componentStyles` to adjust the width of item component.
    * Changed computed property `containerStyles` to adjust slider container based on arrowStyle.
    * Changed computed property `scrollableContainerStyles` to get calculate the scrollable width and the translated amount.
    * Deprecated computed property `arrowStyles`.
    * Added watcher for `initialIndex` to update `currentPageIndex` and `currentItemIndex` when it is changed.
    * Added method `onSetCurrentItem` to update `currentPageIndex` and `currentItemIndex`.
    * Added method `isHiddenItem` to set `aria-hidden` attribute for hidden items.
    * Changed method `goToPreviousImage` to navigate correctly when itemPerPage is higher than 1 or setting rewind mode.
    * Changed method `goToNextImage` to navigate correctly when itemPerPage is higher than 1 or setting rewind mode.
    * Changed method `elementClasses` to set style for image container.
    * Changed method `elementStyles` to set style for image container.
    * Added method `imageClasses` to set class for each image by following BEM convention
* Changed in `src/app/component/media/sw-image-slider/sw-image-slider.scss`.
    * Changed class name `sw-image-slider__image-container-scrollable` to `sw-image-slider__image-scrollable`.
    * Changed class name `sw-image-slider__image-container-element` to `sw-image-slider__element-container`.
    * Changed class name `sw-image-slider__image-container-element-image` to `sw-image-slider__element-image`.
    * Changed class name `sw-image-slider__image-container-element-description` to `sw-image-slider__element-description`
    * Added modifier `is--rounded`, `is--active` to `sw-image-slider__element-container`.
    * Added modifier `is--auto-width`, `is--active` to `sw-image-slider__element-image`.
    * Added modifier `is--button-inside` to `sw-image-slider__buttons`.
* Added `sw-image-preview-component` in `src/app/component/modal/sw-image-preview-modal/index.js`.
* Changed in `src/module/sw-product/component/sw-product-variants/sw-product-variants-media-upload/index.js`.
    * Added method `previewMedia` to show preview modal after clicking on item and selecting "Preview image" option.
    * Added method `onClosePreviewModal` to close preview modal.
* Changed in `src/app/component/base/sw-product-variant-info/index.js`.    
    * Changed computed property `productName` to fix console error.
    * Changed computed property `getFirstSlot` to fix console error.
* Changed method `onMediaInheritanceRemove` in `src/module/sw-product/component/sw-product-variant-modal/index.js` to pass media data to variant media.
