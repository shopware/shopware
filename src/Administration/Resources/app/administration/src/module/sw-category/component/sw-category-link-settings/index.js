import template from './sw-category-link-settings.html.twig';
import './sw-category-link-settings.scss';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('sw-category-link-settings', {
    template,

    inject: ['acl'],

    props: {
        category: {
            type: Object,
            required: true,
        },

        isLoading: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        linkTypeValues() {
            return [
                {
                    value: 'external',
                    label: this.$tc('sw-category.base.link.type.external'),
                },
                {
                    value: 'internal',
                    label: this.$tc('sw-category.base.link.type.internal'),
                },
            ];
        },

        entityValues() {
            return [
                {
                    value: 'category',
                    label: this.$tc('global.entities.category'),
                },
                {
                    value: 'product',
                    label: this.$tc('global.entities.product'),
                },
                {
                    value: 'landing_page',
                    label: this.$tc('global.entities.landing_page'),
                },
            ];
        },

        mainType: {
            get() {
                if (this.isExternal || !this.category.linkType) {
                    return this.category.linkType;
                }

                return 'internal';
            },

            set(value) {
                if (value === 'external') {
                    this.category.internalLink = null;
                } else {
                    this.category.externalLink = null;
                }

                this.category.linkType = value;
            },
        },

        isExternal() {
            return this.category.linkType === 'external';
        },

        isInternal() {
            return !!this.category.linkType && this.category.linkType !== 'external';
        },

        productCriteria() {
            const criteria = new Criteria();
            criteria.addAssociation('options.group');

            return criteria;
        },

        categoryCriteria() {
            const criteria = new Criteria();
            criteria.addFilter(Criteria.not('and', [Criteria.equals('id', this.category.id)]));
            criteria.addFilter(Criteria.equals('type', 'page'));

            return criteria;
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.category.linkType && this.category.externalLink) {
                this.category.linkType = 'external';
            }
        },

        changeEntity() {
            if (!this.category.linkType) {
                this.category.linkType = 'internal';
            }

            this.category.internalLink = null;
        },
    },
});
