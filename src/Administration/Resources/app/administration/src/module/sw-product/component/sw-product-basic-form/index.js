import template from './sw-product-basic-form.html.twig';
import './sw-product-basic-form.scss';

const { Criteria } = Shopware.Data;
const { Component, Context, Mixin } = Shopware;
const { mapPropertyErrors, mapState } = Shopware.Component.getComponentHelper();

Component.register('sw-product-basic-form', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('placeholder'),
    ],

    props: {
        allowEdit: {
            type: Boolean,
            required: false,
            default: true,
        },

        showSettingsInformation: {
            type: Boolean,
            required: false,
            default: true,
        },
    },

    data() {
        return {
            productNumberRangeId: null,
        };
    },

    computed: {
        ...mapState('swProductDetail', [
            'product',
            'parentProduct',
            'loading',
        ]),

        ...mapPropertyErrors('product', [
            'name',
            'description',
            'productNumber',
            'manufacturerId',
            'active',
            'markAsTopseller',
        ]),

        numberRangeRepository() {
            return this.repositoryFactory.create('number_range');
        },

        isTitleRequired() {
            return Shopware.State.getters['context/isSystemDefaultLanguage'];
        },

        productNumberRangeLink() {
            if (!this.productNumberRangeId) {
                return {
                    name: 'sw.settings.number.range.index',
                };
            }

            return {
                name: 'sw.settings.number.range.detail',
                params: { id: this.productNumberRangeId },
            };
        },

        productNumberHelpText() {
            return this.$tc('sw-product.basicForm.productNumberHelpText.label', 0, {
                link: `<sw-internal-link
                           :router-link=${JSON.stringify(this.productNumberRangeLink)}
                           :inline="true">
                           ${this.$tc('sw-product.basicForm.productNumberHelpText.linkText')}
                       </sw-internal-link>`,
            });
        },

        highlightHelpText() {
            const themesLink = {
                name: 'sw.theme.manager.index',
            };

            const snippetLink = {
                name: 'sw.settings.snippet.detail',
                params: { key: 'listing.boxLabelTopseller' },
            };

            return this.$tc('sw-product.basicForm.highlightHelpText.label', 0, {
                themesLink: `<sw-internal-link
                                 :router-link=${JSON.stringify(themesLink)}
                                 :inline="true">
                                 ${this.$tc('sw-product.basicForm.highlightHelpText.themeLinkText')}
                             </sw-internal-link>`,
                snippetLink: `<sw-internal-link
                                  :router-link=${JSON.stringify(snippetLink)}
                                  :inline="true">
                                  ${this.$tc('sw-product.basicForm.highlightHelpText.snippetLinkText')}
                              </sw-internal-link>`,
            });
        },

        numberRangeCriteria() {
            const criteria = new Criteria();

            criteria.addFilter(Criteria.equals('type.technicalName', 'product'));
            criteria.addFilter(Criteria.equals('global', true));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.loadProductNumberRangeId();
        },

        updateIsTitleRequired() {
            // TODO: Refactor when there is a possibility to check if the title field is inherited
            this.isTitleRequired = Shopware.Context.api.languageId === Shopware.Context.api.systemLanguageId;
        },

        getInheritValue(firstKey, secondKey) {
            const p = this.parentProduct;

            if (p[firstKey]) {
                return p[firstKey].hasOwnProperty(secondKey) ? p[firstKey][secondKey] : p[firstKey];
            }
            return null;
        },

        loadProductNumberRangeId() {
            return this.numberRangeRepository.searchIds(this.numberRangeCriteria, Context.api).then((numberRangeIds) => {
                this.productNumberRangeId = numberRangeIds.data[0];
            });
        },
    },
});
