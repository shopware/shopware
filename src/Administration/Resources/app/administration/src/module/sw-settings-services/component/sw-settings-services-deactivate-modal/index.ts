import template from './sw-settings-services-deactivate-modal.html.twig';
import './sw-settings-services-deactivate-modal.scss';
import extractError from '../../composables/extract-error';

/**
 * @sw-package framework
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    name: 'sw-settings-services-deactivate-modal',
    template,

    props: {
        feedbackLink: {
            type: String,
        },
    },

    data() {
        return {
            isLoading: false,
        };
    },

    methods: {
        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },

        async disableServices(done: () => void) {
            this.isLoading = true;

            try {
                const shopwareServicesService = Shopware.Service('shopwareServicesService');

                await shopwareServicesService.disableAllServices();

                this._reloadPage();
            } catch (exceptionResponse) {
                Shopware.Store.get('notification').createNotification({
                    title: this.$t('global.default.error'),
                    variant: 'critical',
                    message: extractError(exceptionResponse),
                    autoClose: false,
                });
            } finally {
                this.isLoading = false;
            }

            done();
        },
    },
});
