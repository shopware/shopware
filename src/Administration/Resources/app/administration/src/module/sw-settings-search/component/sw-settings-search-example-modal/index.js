/**
 * @sw-package inventory
 */
import template from './sw-settings-search-example-modal.html.twig';
import './sw-settings-search-example-modal.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    emits: ['modal-close'],

    data() {
        return {
            exampleResults: [
                {
                    textTitle: this.$t('sw-settings-search.generalTab.modal.textTitle'),
                    textSuperProductName: this.$t('sw-settings-search.generalTab.modal.textSuperJeans'),
                    scoreSuperProductName: 100,
                    textDescription: this.$t('sw-settings-search.generalTab.modal.textDescription'),
                    textProductName: this.$t('sw-settings-search.generalTab.modal.textFancyJeans'),
                    scoreProductName: 50,
                    textTag: this.$t('sw-settings-search.generalTab.modal.textTag'),
                    textDetailName: this.$t('sw-settings-search.generalTab.modal.textJeans'),
                    scoreDetail: 20,
                    textTotal: this.$t('sw-settings-search.generalTab.modal.textTotal'),
                    scoreTotal: 170,
                    textProductRankedScore: this.$t('sw-settings-search.generalTab.modal.textProductRankedFirstScore'),
                },
                {
                    textTitle: this.$t('sw-settings-search.generalTab.modal.textTitle'),
                    textSuperProductName: this.$t('sw-settings-search.generalTab.modal.textSuperJeans'),
                    scoreSuperProductName: 100,
                    textDescription: this.$t('sw-settings-search.generalTab.modal.textDescription'),
                    textProductName: this.$t('sw-settings-search.generalTab.modal.textFancyPants'),
                    scoreProductName: 0,
                    textTag: this.$t('sw-settings-search.generalTab.modal.textTag'),
                    textDetailName: this.$t('sw-settings-search.generalTab.modal.textJeans'),
                    scoreDetail: 20,
                    textTotal: this.$t('sw-settings-search.generalTab.modal.textTotal'),
                    scoreTotal: 120,
                    textProductRankedScore: this.$t('sw-settings-search.generalTab.modal.textProductRankedSecondScore'),
                },
                {
                    textTitle: this.$t('sw-settings-search.generalTab.modal.textTitle'),
                    textSuperProductName: this.$t('sw-settings-search.generalTab.modal.textSuperPants'),
                    scoreSuperProductName: 0,
                    textDescription: this.$t('sw-settings-search.generalTab.modal.textDescription'),
                    textProductName: this.$t('sw-settings-search.generalTab.modal.textFancyPants'),
                    scoreProductName: 0,
                    textTag: this.$t('sw-settings-search.generalTab.modal.textTag'),
                    textDetailName: this.$t('sw-settings-search.generalTab.modal.textJeans'),
                    scoreDetail: 20,
                    textTotal: this.$t('sw-settings-search.generalTab.modal.textTotal'),
                    scoreTotal: 20,
                    textProductRankedScore: this.$t('sw-settings-search.generalTab.modal.textProductRankedThirdScore'),
                },
            ],
        };
    },

    methods: {
        closeModal() {
            this.$emit('modal-close');
        },
    },
};
