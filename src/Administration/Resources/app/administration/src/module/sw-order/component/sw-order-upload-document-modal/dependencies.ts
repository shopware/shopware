/**
 * @sw-package after-sales
 *
 * Everything sw-order-upload-document-modal needs from outside its mini-module.
 */
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { default as RepositoryType } from 'src/core/data/repository.data';
/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification';
/** @private */
export { documentV2Service, numberRangeService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export type { AvailableDocumentTypesResponse } from '../../../../core/service/api/documentV2.api.service';
/** @private */
export { DOCUMENT_TYPES, FILE_FORMAT_MIME_TYPES } from '../../service/documentV2.service';
/** @private */
export type { DocumentConfig } from '../../service/documentV2.service';
