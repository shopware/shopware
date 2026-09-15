import template from './sw-settings-document-list.html.twig';
import './sw-settings-document-list.scss';

const {
    Mixin,
    Data: { Criteria },
} = Shopware;

/**
 * @sw-package after-sales
 */
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
            /**
             * @deprecated tag:v6.8.0 - Will be removed without replacement
             */
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

        listingCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            if (this.term) {
                criteria.setTerm(this.term);
            }

            criteria.addAssociation('documentType').getAssociation('salesChannels').addAssociation('salesChannel');

            criteria.addSorting(Criteria.sort('name', 'ASC', false));

            return criteria;
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        expandButtonClass() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-settings-document-list.expandButtonClass is deprecated. Will be removed without replacement.',
            );

            return {
                'is--hidden': this.expanded,
            };
        },

        /**
         * @deprecated tag:v6.8.0 - Will be removed without replacement
         */
        collapseButtonClass() {
            Shopware.Feature.triggerDeprecationOrThrow(
                'V6_8_0_0',
                'sw-settings-document-list.collapseButtonClass is deprecated. Will be removed without replacement.',
            );

            return {
                'is--hidden': !this.expanded,
            };
        },
    },
};
