import template from './sw-category-detail-base.html.twig';
import './sw-category-detail-base.scss';

const { mapState, mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @package content
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'repositoryFactory',
        'acl',
    ],

    mixins: [
        'placeholder',
    ],

    props: {
        isLoading: {
            type: Boolean,
            required: true,
        },
    },

    computed: {
        ...mapState('swCategoryDetail', {
            customFieldSetsArray: state => {
                if (!state.customFieldSets) {
                    return [];
                }

                return state.customFieldSets;
            },
        }),

        ...mapPropertyErrors('category', [
            'name',
            'type',
        ]),

        categoryTypes() {
            return [
                {
                    value: 'page',
                    label: this.$tc('sw-category.base.general.types.page'),
                },
                {
                    value: 'folder',
                    label: this.$tc('sw-category.base.general.types.folder'),
                },
                // @todo NEXT-22697 - Re-implement, when re-enabling cms-aware
                // {
                //     value: 'custom_entity',
                //     label: this.$tc('sw-category.base.general.types.customEntity'),
                // },
                {
                    value: 'link',
                    label: this.typeLinkLabel,
                    disabled: this.isSalesChannelEntryPoint,
                },
            ];
        },

        typeLinkLabel() {
            if (this.isSalesChannelEntryPoint) {
                return this.$tc('sw-category.base.general.types.linkUnavailable');
            }

            return this.$tc('sw-category.base.general.types.link');
        },

        categoryTypeHelpText() {
            if (['page', 'folder', 'link'].includes(this.category.type)) {
                return this.$tc(`sw-category.base.general.types.helpText.${this.category.type}`);
            }

            return null;
        },

        isSalesChannelEntryPoint() {
            return this.category.navigationSalesChannels.length > 0
                || this.category.serviceSalesChannels.length > 0
                || this.category.footerSalesChannels.length > 0;
        },

        category() {
            return Shopware.State.get('swCategoryDetail').category;
        },
    },
};
