import template from './sw-settings-document-list.html.twig';
import './sw-settings-document-list.scss';

const { Component, Mixin, Data: { Criteria } } = Shopware;

Component.register('sw-settings-document-list', {
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
            const criteria = new Criteria();

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
});
