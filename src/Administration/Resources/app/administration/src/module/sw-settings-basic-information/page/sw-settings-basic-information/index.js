import template from './sw-settings-basic-information.html.twig';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    mixins: [
        Mixin.getByName('notification'),
    ],


    data() {
        return {
            isLoading: false,
            isSaveSuccessful: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    methods: {
        saveFinish() {
            this.isSaveSuccessful = false;
        },

        onSave() {
            this.isSaveSuccessful = false;
            this.isLoading = true;

            this.$refs.systemConfig.saveAll().then(() => {
                this.isLoading = false;
                this.isSaveSuccessful = true;
            }).catch((err) => {
                this.isLoading = false;
                this.createNotificationError({
                    message: err,
                });
            });
        },

        onLoadingChanged(loading) {
            this.isLoading = loading;
        },

        /**
         * @deprecated tag:v6.7.0 - Will be removed
         */
        abortOnLanguageChange() {
            // We don't know if there are changes. So show the warning everytime.
            return true;
        },

        /**
         * @deprecated tag:v6.7.0 - Will be removed
         */
        saveOnLanguageChange() {
            return this.onSave();
        },

        /**
         * @deprecated tag:v6.7.0 - Will be removed
         */
        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);

            this.$refs.systemConfig.createdComponent();
        },
    },
};
