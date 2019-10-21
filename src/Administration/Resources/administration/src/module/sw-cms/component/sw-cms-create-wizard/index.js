import template from './sw-cms-create-wizard.html.twig';
import './sw-cms-create-wizard.scss';

const { Component } = Shopware;

Component.register('sw-cms-create-wizard', {
    template,

    props: {
        page: {
            type: Object,
            required: true
        }
    },

    data() {
        return {
            step: 1,
            pageTypeNames: {
                page: this.$tc('sw-cms.detail.labelPageTypeShopPage'),
                landingpage: this.$tc('sw-cms.detail.labelPageTypeLandingpage'),
                product_list: this.$tc('sw-cms.detail.labelPageTypeCategory'),
                product_detail: this.$tc('sw-cms.detail.labelPageTypeProduct')
            },
            pageTypeIcons: {
                page: 'default-object-lightbulb',
                landingpage: 'default-web-dashboard',
                product_list: 'default-shopping-basket',
                product_detail: 'default-action-tags'
            }
        };
    },

    computed: {
        pagePreviewMedia() {
            if (this.page.sections.length < 1) {
                return '';
            }

            const context = Shopware.Context;
            const imgPath = 'administration/static/img/cms';

            return `url(${context.assetsPath}/${imgPath}/preview_${this.page.type}_${this.page.sections[0].type}.png)`;
        },

        pagePreviewStyle() {
            return {
                'background-image': this.pagePreviewMedia,
                'background-size': 'cover'
            };
        }
    },

    methods: {
        getPageTypeName() {
            return this.pageTypeNames[this.page.type];
        },


        getPageIconName() {
            return this.pageTypeIcons[this.page.type];
        },

        onPageTypeSelect(type) {
            this.page.type = type;
            this.step = 2;
        },

        onSectionSelect(section) {
            this.step = 3;
            this.$emit('on-section-select', section);
        },

        onCompletePageCreation() {
            this.$emit('wizard-complete');
        },

        goStepBack() {
            if (this.step === 3) {
                this.page.sections = [];
            }

            this.step -= 1;
        }
    }
});
