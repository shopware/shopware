/**
 * @sw-package framework
 */
import { MtModal, MtModalRoot, MtModalAction, MtModalClose } from '@shopware-ag/meteor-component-library';
import DOMPurify from 'dompurify';
import useConsentStore from 'src/core/consent/consent.store';
import useNotificationStore from 'src/app/store/notification.store';
import { sendConsentRequestResponse } from 'src/core/consent/sdk-handler';
import template from './sw-request-consent-modal.html.twig';
import './sw-request-consent-modal.scss';

/**
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-request-consent-modal',
    template,

    components: { MtModal, MtModalRoot, MtModalAction, MtModalClose },

    data() {
        return {
            isLoading: false,
        };
    },

    computed: {
        hasConsentRequest() {
            return useConsentStore().consentRequestInfo.length > 0;
        },
        state() {
            return useConsentStore().consentRequestInfo[0] ?? null;
        },

        sanitizedMessage() {
            if (!this.state || !this.state.consentRequest.requestMessage) {
                return null;
            }

            return DOMPurify.sanitize(this.state.consentRequest.requestMessage, {
                ALLOWED_TAGS: [
                    'a',
                    'b',
                    'strong',
                    'i',
                    'em',
                    'li',
                    'ul',
                ],
            });
        },
    },

    methods: {
        async accept() {
            if (!this.state) {
                return;
            }

            const consentStore = useConsentStore();
            this.isLoading = true;

            try {
                await consentStore.accept(this.state.consentRequest.consent);
            } catch (e: unknown) {
                const notificationStore = useNotificationStore();
                notificationStore.createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-request-consent-modal.updateFailed'),
                    autoClose: false,
                });

                throw e;
            } finally {
                sendConsentRequestResponse(
                    this.state.requester.window,
                    this.state.consentRequest.requestId,
                    consentStore.consents[this.state.consentRequest.consent],
                );
                consentStore.removeConsentRequest();
                this.isLoading = false;
            }
        },

        async decline() {
            if (!this.state) {
                return;
            }

            const consentStore = useConsentStore();
            this.isLoading = true;

            try {
                await consentStore.revoke(this.state.consentRequest.consent);
            } catch (e: unknown) {
                const notificationStore = useNotificationStore();
                notificationStore.createNotification({
                    variant: 'critical',
                    title: this.$t('global.default.error'),
                    message: this.$t('sw-request-consent-modal.updateFailed'),
                    autoClose: false,
                });

                throw e;
            } finally {
                sendConsentRequestResponse(
                    this.state.requester.window,
                    this.state.consentRequest.requestId,
                    consentStore.consents[this.state.consentRequest.consent],
                );
                consentStore.removeConsentRequest();
                this.isLoading = false;
            }
        },
    },
});
