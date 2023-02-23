import template from './sw-extension-sdk-module.html.twig';

/**
 * @private Only to be used by the Admin extension API
 */
Shopware.Component.register('sw-extension-sdk-module', {
    template,

    props: {
        id: {
            type: String,
            required: true,
        },
    },

    data() {
        return {
            timedOut: false,
            loadingTimeOut: null,
            isLoaded: false,
        };
    },

    computed: {
        module() {
            return Shopware.State.get('extensionSdkModules').modules.find(module => module.id === this.id);
        },

        isLoading() {
            return !this.isLoaded && !this.timedOut;
        },

        showSearchBar() {
            return this.module?.displaySearchBar ?? true;
        },
    },

    watch: {
        $route() {
            this.load();
        },
    },

    /**
     * This component should not be extendable therefore no createdComponent() hook.
     */
    created() {
        // Keep threshold synced with admin extension sdk
        this.loadingTimeOut = window.setTimeout(() => {
            this.timedOut = true;
            this.loadingTimeOut = null;
        }, 7000);
    },

    beforeDestroy() {
        if (this.loadingTimeOut) {
            window.clearTimeout(this.loadingTimeOut);
        }
    },

    methods: {
        onLoad() {
            this.isLoaded = true;
            window.clearTimeout(this.loadingTimeOut);
        },
        load() {
            this.timedOut = false;
            this.isLoaded = false;
            if (!this.$refs.iframeRenderer) {
                return;
            }

            this.$refs.iframeRenderer.loadIframeSrc();
        },
    },
});
