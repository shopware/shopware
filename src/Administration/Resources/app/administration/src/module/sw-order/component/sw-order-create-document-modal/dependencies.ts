/**
 * @sw-package after-sales
 *
 * Everything sw-order-create-document-modal needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { documentV2Service, numberRangeService } from 'src/app/service-names';
/** @private */
export type { AvailableDocumentTypesResponse } from '../../../../core/service/api/documentV2.api.service';
/** @private */
export { DOCUMENT_TYPES, FILE_FORMATS, INVOICE_DOCUMENT_TYPES } from '../../service/documentV2.service';
/** @private */
export type { DeliveryNoteConfig, DocumentConfig } from '../../service/documentV2.service';
