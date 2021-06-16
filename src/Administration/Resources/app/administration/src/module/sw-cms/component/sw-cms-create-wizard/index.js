import template from './sw-cms-create-wizard.html.twig';
import './sw-cms-create-wizard.scss';

const { Component, Filter } = Shopware;

Component.register('sw-cms-create-wizard', {
    template,

    inject: ['feature'],

    props: {
        page: {
            type: Object,
            required: true,
        },
    },

    data() {
        const pageTypeNames = {
            page: this.$tc('sw-cms.detail.label.pageTypeShopPage'),
            landingpage: this.$tc('sw-cms.detail.label.pageTypeLandingpage'),
            product_list: this.$tc('sw-cms.detail.label.pageTypeCategory'),
            product_detail: this.$tc('sw-cms.detail.label.pageTypeProduct'),
        };

        const pageTypeIcons = {
            page: 'default-object-lightbulb',
            landingpage: 'default-web-dashboard',
            product_list: 'default-shopping-basket',
            product_detail: 'default-action-tags',
        };

        return {
            step: 1,
            pageTypeNames,
            pageTypeIcons,
            steps: {
                pageType: 1,
                sectionType: 2,
                pageName: 3,
            },
        };
    },

    computed: {
        pagePreviewMedia() {
            if (this.page.sections.length < 1) {
                return '';
            }

            const imgPath = 'administration/static/img/cms';

            return `url(${this.assetFilter(`${imgPath}/preview_${this.page.type}_${this.page.sections[0].type}.png`)})`;
        },

        pagePreviewStyle() {
            return {
                'background-image': this.pagePreviewMedia,
                'background-size': 'cover',
            };
        },

        assetFilter() {
            return Filter.getByName('asset');
        },
    },

    watch: {
        step(newStep) {
            if (this.getStepName(newStep) === 'sectionType') {
                this.page.sections = [];
            }
        },
    },

    methods: {
        goToStep(stepName) {
            this.step = this.steps[stepName];
        },

        getStepName(stepValue) {
            const find = Object.entries(this.steps).find((step) => {
                return stepValue === step[1];
            });

            if (!find) {
                return '';
            }

            return find[0];
        },

        getPageTypeName() {
            return this.pageTypeNames[this.page.type];
        },


        getPageIconName() {
            return this.pageTypeIcons[this.page.type];
        },

        onPageTypeSelect(type) {
            this.page.type = type;

            this.goToStep('sectionType');
        },

        onSectionSelect(section) {
            this.goToStep('pageName');

            this.$emit('on-section-select', section);
        },

        onCompletePageCreation() {
            if (!this.page.name) {
                return;
            }

            this.$emit('wizard-complete');
        },
    },
});
