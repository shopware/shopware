import template from './sw-extension-store-landing-page.html.twig';
import './sw-extension-store-landing-page.scss';

/**
 * @private
 */
Shopware.Component.register('sw-extension-store-landing-page', {
    template,

    inject: ['extensionHelperService'],

    data() {
        return {
            isLoading: false,
            activationStatus: null,
            error: null,
        };
    },

    computed: {
        extensionName() {
            return 'SwagExtensionStore';
        },
    },

    methods: {

        activateStore() {
            this.isLoading = true;
            this.activationStatus = null;

            this.extensionHelperService.downloadAndActivateExtension(this.extensionName)
                .then(() => {
                    this.activationStatus = 'success';
                    window.location.reload();
                })
                .catch(error => {
                    this.activationStatus = 'error';

                    if (error?.response?.data &&
                        Array.isArray(error.response.data.errors) &&
                        error.response.data.errors[0]
                    ) {
                        this.error = error.response.data.errors[0];
                    }

                    Shopware.Utils.debug.error(error);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        },
    },
});
