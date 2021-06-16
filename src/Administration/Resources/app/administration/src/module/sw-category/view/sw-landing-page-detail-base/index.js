import template from './sw-landing-page-detail-base.html.twig';
import './sw-landing-page-detail-base.scss';

const { Component, Mixin } = Shopware;
const { mapState, mapPropertyErrors } = Shopware.Component.getComponentHelper();

Component.register('sw-landing-page-detail-base', {
    template,

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
        landingPage() {
            return Shopware.State.get('swCategoryDetail').landingPage;
        },

        cmsPage() {
            return Shopware.State.get('cmsPageState').currentPage;
        },

        ...mapPropertyErrors('landingPage', [
            'name',
            'url',
            'salesChannels',
        ]),

        ...mapState('swCategoryDetail', {
            customFieldSetsArray: state => {
                if (!state.customFieldSets) {
                    return [];
                }

                return state.customFieldSets;
            },
        }),
    },
});
