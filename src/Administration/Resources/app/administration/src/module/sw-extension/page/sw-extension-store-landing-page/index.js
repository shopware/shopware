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
            activationStatus: null
        };
    },

    computed: {
        extensionName() {
            return 'SwagExtensionStore';
        }
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
                    Shopware.Utils.debug.error(error);
                })
                .finally(() => {
                    this.isLoading = false;
                });
        }
    }
});
