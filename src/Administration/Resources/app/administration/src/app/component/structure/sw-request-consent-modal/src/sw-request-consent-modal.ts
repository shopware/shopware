/**
 * @sw-package framework
 */
import template from './sw-request-consent-modal.html.twig';
import './sw-request-consent-modal.scss';
import {
    DOMPurify,
    MtModal,
    MtModalAction,
    MtModalClose,
    MtModalRoot,
    sendConsentRequestResponse,
    useConsentStore,
    useNotificationStore,
    wrapComponentConfig,
} from '../dependencies';

/**
 * @private
 */
export default wrapComponentConfig({
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
