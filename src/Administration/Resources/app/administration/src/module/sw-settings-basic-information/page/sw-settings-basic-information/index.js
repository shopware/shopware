import template from './sw-settings-basic-information.html.twig';

const { Mixin } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

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

        abortOnLanguageChange() {
            // We don't know if there are changes. So show the warning everytime.
            return true;
        },

        saveOnLanguageChange() {
            return this.onSave();
        },

        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);

            this.$refs.systemConfig.createdComponent();
        },
    },
};
