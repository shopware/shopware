import template from './sw-landing-page-detail-base.html.twig';
import './sw-landing-page-detail-base.scss';

const { Mixin } = Shopware;
const { mapState, mapPropertyErrors } = Shopware.Component.getComponentHelper();

/**
 * @package inventory
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    compatConfig: Shopware.compatConfig,

    inject: ['repositoryFactory', 'acl'],

    mixins: [
        Mixin.getByName('placeholder'),
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

        ...mapPropertyErrors('landingPage', [
            'name',
            'url',
            'salesChannels',
        ]),

        landingPage() {
            return Shopware.State.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopware.Store.get('cmsPageState').currentPage;
        },

        isLayoutSet() {
            return this.landingPage.cmsPageId !== null;
        },
    },
};
