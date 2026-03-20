import template from './sw-extension-store-landing-page.html.twig';
import './sw-extension-store-landing-page.scss';

/**
 * @sw-package checkout
 * @private
 */
export default {
    template,

    inject: ['extensionHelperService'],

    props: {
        insideModal: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

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

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    methods: {
        /** Thin wrapper so tests can spy on navigation without mocking window.location (non-configurable in JSDOM v26). */
        _reloadPage() {
            window.location.reload();
        },

        activateStore() {
            this.isLoading = true;
            this.activationStatus = null;

            this.extensionHelperService
                .downloadAndActivateExtension(this.extensionName)
                .then(() => {
                    this.activationStatus = 'success';
                    this._reloadPage();
                })
                .catch((error) => {
                    this.activationStatus = 'error';

                    if (
                        error?.response?.data &&
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
};
