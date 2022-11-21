import template from './sw-settings-document-list.html.twig';
import './sw-settings-document-list.scss';

const { Mixin, Data: { Criteria } } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl'],

    mixins: [
        Mixin.getByName('sw-settings-list'),
    ],

    data() {
        return {
            entityName: 'document_base_config',
            sortBy: 'document_base_config.name',
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        filters() {
            return [];
        },
        expandButtonClass() {
            return {
                'is--hidden': this.expanded,
            };
        },
        collapseButtonClass() {
            return {
                'is--hidden': !this.expanded,
            };
        },
        listingCriteria() {
            const criteria = new Criteria(1, 25);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            criteria
                .addAssociation('documentType')
                .getAssociation('salesChannels')
                .addAssociation('salesChannel');

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },
    },

    methods: {
        onChangeLanguage(languageId) {
            Shopware.State.commit('context/setApiLanguageId', languageId);

            this.getList();
        },
    },
};
