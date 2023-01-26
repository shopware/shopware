---
title: Modify create document modal
issue: NEXT-16680
---
# Administration
* Changed in component `sw–media-upload-v2`
  * Added props `useFileData` to allow using value as File object and emit files from `media-upload-add-file` event
  * Added method `checkFileType` to check uploaded file extension.
  * Added method `checkFileSize` to check uploaded file size.
  * Changed props `source` to add props type File.
  * Changed props `variant` to add new variant type `small`.
  * Added props `maxFileSize` to limit upload file size.
  * Added props `useFileData` to prevent saving file to database after choosing file from local machine.
  * Added data variable `isCorrectFileType`
  * Added method `handleFileCheck` to check if file size and file type are matched.
  * Changed method `onDragLeave` to reset active state when dragging file out of drop zone.
  * Changed method `onFileInputChange` to validate files before uploading or setting file.
  
* Changed in `src/app/component/media/sw-media-upload-v2/sw-media-upload-v2.scss`
  * Added modifier `is--small` to `sw-media-upload-v2__actions`, `sw-media-upload-v2__button`, `sw-media-upload-v2__dropzone`
  
* Changed in component `sw-file-input`
  * Added props `disabled` to disabled interact with component
  * Added method `mountedComponent` 
  * Added method `onDragEnter` to handle when dragging file to drop zone
  * Added method `onDragLeave` to handle when dragging file out of drop zone.
  * Added method `stopEventPropagation` to handle file input state after dropping file.
  * Added method `onDrop` to set file after dropping file in drop zone
  * Added computed `isDragActiveClass` show active state of drop zone when file is moved into it.
  
* Changed in `src/app/component/form/sw-file-input/sw-file-input.scss`
  * Added `is--active` modifier for class `sw-file-input__dropzone` to highlight dropzone.

* Changed in `src/module/sw-order/component/sw-order-document-card/index.js`
  * Added computed property `showCardFilter` to handle showing card filter of document card.
  * Added computed property `showCreateDocumentButton` to handle showing create document button of document card.
  * Added computed property `emptyStateTitle` to handle showing empty state of document card.
  * Changed method `onCreateDocument` to add custom document from media library or from url, to allow user download or sent email after creating document.

* Changed in `src/module/sw-order/component/sw-order-document-card/sw-order-document-card.html.twig`
  * Changed block `sw_order_document_card_header`, block `sw_order_document_card_header_filter` to handle showing card filter of document card. 
  * Changed block `sw_order_document_card_empty_state` to add new UI of empty state.

* Changed in `src/module/sw-order/component/sw-order-document-settings-modal/index.js`
  * Added computed property `modalTitle` to show modal title responding to document type.
  * Added computed property `fileAcceptTypes` to apply for `fileAccept` props of `sw-media-upload-v2` component
  * Added computed property `mediaRepository`
  * Added method `openMediaModal` to open media modal.
  * Added method `closeMediaModal` to close media modal.
  * Added method `onAddMediaFromLibrary` to add media from modal.
  * Added method `successfulUploadFromUrl` to add media uploaded from url.
  * Added method `validateFile` to validate file size and file type.
  * Added method `removeCustomDocument` to reset `documentMediaFileId` configuration.
  
* Changed in `src/module/sw-order/component/sw-order-document-settings-modal/sw-order-document-settings-modal.html.twig`
  * Added block `sw_order_document_settings_modal_form_file_upload_toggle` to cover custom document toggle
  * Added block `sw_order_document_settings_modal_form_file_upload_input` to cover custom document file input
  * Added block `sw_order_document_settings_modal_media_modal` to add media modal component
  * Changed block `sw_order_document_settings_modal_form_document_footer_split_button_context_create_send` and block `sw_order_document_settings_modal_form_document_footer_split_button_context_create_download` to show `Create and send` and `Create and download` context menu.
* Added file `src/module/sw-order/component/sw-order-document-settings-modal/sw-order-document-settings-modal.scss`
    
* Changed in `src/module/sw-order/component/sw-order-document-settings-delivery-note-modal/sw-order-document-settings-delivery-note-modal.html.twig`.
  * Deprecated block `sw_order_document_settings_modal_form_document_number_before`
  * Changed block `sw_order_document_settings_modal_form_document_number` to rearrange fields
  
* Changed method `createdComponent` in `src/module/sw-order/component/sw-order-document-settings-credit-note-modal/index.js` to filter invoice document options.
* Changed in `src/module/sw-order/component/sw-order-document-settings-credit-note-modal/sw-order-document-settings-credit-note-modal.html.twig`.
  * Added block `sw_order_document_settings_modal_form_first_row`
  * Deprecated `sw_order_document_settings_modal_form_document_number` and `sw_order_document_settings_modal_form_additional_content`
  
* Changed in `src/module/sw-order/component/sw-order-document-settings-storno-modal/sw-order-document-settings-storno-modal.html.twig`
  * Changed block `sw_order_document_settings_modal_form_first_row` to rearrange fields
  * Deprecated block `sw_order_document_settings_modal_form_document_number`
  * Deprecated block `sw_sales_channel_detail_base_general_input_type`
  * Deprecated block `sw_order_document_settings_modal_form_document_date`

* Changed method `createDocument` in `src/core/service/api/document.api.service.js` to allow using document file from media library or external URL.
* Changed in `src/module/sw-order/component/sw-order-document-card/index.js`
  * Added computed property `showCardFilter`
  * Added computed property `showCreateDocumentButton`
  * Added computed property `emptyStateTitle`
  * Changed method `onCreateDocument` to allow `create and download` or `create and send option`.

