/**
 * @sw-package framework
 *
 * Everything sw-request-consent-modal needs from outside its mini-module.
 */
/** @private */
export { MtModal, MtModalAction, MtModalClose, MtModalRoot } from '@shopware-ag/meteor-component-library';
/** @private */
export { default as DOMPurify } from 'dompurify';
/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { default as useConsentStore } from 'src/core/consent/consent.store';
/** @private */
export { sendConsentRequestResponse } from 'src/core/consent/sdk-handler';
/** @private */
export { useNotificationStore as useNotificationStore } from 'src/app/store/notification';
