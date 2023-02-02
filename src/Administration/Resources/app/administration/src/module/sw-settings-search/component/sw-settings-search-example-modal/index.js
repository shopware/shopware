/**
 * @package system-settings
 */
import template from './sw-settings-search-example-modal.html.twig';
import './sw-settings-search-example-modal.scss';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    data() {
        return {
            exampleResults: [
                {
                    textTitle: this.$tc('sw-settings-search.generalTab.modal.textTitle'),
                    textSuperProductName: this.$tc('sw-settings-search.generalTab.modal.textSuperJeans'),
                    scoreSuperProductName: 100,
                    textDescription: this.$tc('sw-settings-search.generalTab.modal.textDescription'),
                    textProductName: this.$tc('sw-settings-search.generalTab.modal.textFancyJeans'),
                    scoreProductName: 50,
                    textTag: this.$tc('sw-settings-search.generalTab.modal.textTag'),
                    textDetailName: this.$tc('sw-settings-search.generalTab.modal.textJeans'),
                    scoreDetail: 20,
                    textTotal: this.$tc('sw-settings-search.generalTab.modal.textTotal'),
                    scoreTotal: 170,
                    textProductRankedScore: this.$tc('sw-settings-search.generalTab.modal.textProductRankedFirstScore'),
                },
                {
                    textTitle: this.$tc('sw-settings-search.generalTab.modal.textTitle'),
                    textSuperProductName: this.$tc('sw-settings-search.generalTab.modal.textSuperJeans'),
                    scoreSuperProductName: 100,
                    textDescription: this.$tc('sw-settings-search.generalTab.modal.textDescription'),
                    textProductName: this.$tc('sw-settings-search.generalTab.modal.textFancyPants'),
                    scoreProductName: 0,
                    textTag: this.$tc('sw-settings-search.generalTab.modal.textTag'),
                    textDetailName: this.$tc('sw-settings-search.generalTab.modal.textJeans'),
                    scoreDetail: 20,
                    textTotal: this.$tc('sw-settings-search.generalTab.modal.textTotal'),
                    scoreTotal: 120,
                    textProductRankedScore: this.$tc('sw-settings-search.generalTab.modal.textProductRankedSecondScore'),
                },
                {
                    textTitle: this.$tc('sw-settings-search.generalTab.modal.textTitle'),
                    textSuperProductName: this.$tc('sw-settings-search.generalTab.modal.textSuperPants'),
                    scoreSuperProductName: 0,
                    textDescription: this.$tc('sw-settings-search.generalTab.modal.textDescription'),
                    textProductName: this.$tc('sw-settings-search.generalTab.modal.textFancyPants'),
                    scoreProductName: 0,
                    textTag: this.$tc('sw-settings-search.generalTab.modal.textTag'),
                    textDetailName: this.$tc('sw-settings-search.generalTab.modal.textJeans'),
                    scoreDetail: 20,
                    textTotal: this.$tc('sw-settings-search.generalTab.modal.textTotal'),
                    scoreTotal: 20,
                    textProductRankedScore: this.$tc('sw-settings-search.generalTab.modal.textProductRankedThirdScore'),
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
