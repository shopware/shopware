/**
 * @package system-settings
 */
import template from './sw-settings-number-range-create.html.twig';

const utils = Shopware.Utils;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    beforeRouteEnter(to, from, next) {
        if (to.name.includes('sw.settings.number.range.create') && !to.params.id) {
            to.params.id = utils.createId();
        }

        next();
    },

    methods: {
        createdComponent() {
            if (!Shopware.State.getters['context/isSystemDefaultLanguage']) {
                Shopware.State.commit('context/resetLanguageToDefault');
            }

            if (this.$route.params.id) {
                this.numberRange = this.numberRangeRepository.create(Shopware.Context.api, this.$route.params.id);
            } else {
                this.numberRange = this.numberRangeRepository.create();
            }
            this.numberRange.start = 1;
            this.numberRange.global = false;
            this.numberRange.pattern = '';
            this.numberRange.isLoading = true;
            this.numberRange.type = this.numberRangeTypeRepository.create();
            this.numberRange.type.global = false;

            this.$super('createdComponent');
            this.getPreview();
            this.splitPattern();
            this.onChangePattern();
            this.numberRange.isLoading = false;
        },

        saveFinish() {
            this.isSaveSuccessful = false;
            this.$router.push({ name: 'sw.settings.number.range.detail', params: { id: this.numberRange.id } });
        },

        onSave() {
            this.$super('onSave');
        },
    },
};
