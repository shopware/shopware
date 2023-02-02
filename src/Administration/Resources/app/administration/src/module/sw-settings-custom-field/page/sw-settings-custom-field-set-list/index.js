/**
 * @package system-settings
 */
import template from './sw-settings-custom-field-set-list.html.twig';
import './sw-settings-custom-field-set-list.scss';

const { Locale, Mixin, Data: { Criteria } } = Shopware;

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['acl', 'feature'],

    mixins: [
        Mixin.getByName('sw-inline-snippet'),
        Mixin.getByName('sw-settings-list'),
    ],

    data() {
        return {
            entityName: 'custom_field_set',
            sortBy: 'config.name',
            datetime: '',
            showModal: false,
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle(),
        };
    },

    computed: {
        // Settings Listing mixin override
        titleSaveSuccess() {
            return this.$tc('global.default.success');
        },

        // Settings Listing mixin override
        messageSaveSuccess() {
            if (this.deleteEntity) {
                return this.$tc(
                    'sw-settings-custom-field.set.list.messageDeleteSuccess',
                    0,
                    { name: this.getInlineSnippet(this.deleteEntity.config.label) || this.deleteEntity.name },
                );
            }
            return '';
        },

        listingCriteria() {
            const criteria = new Criteria(this.page, this.limit);

            const params = this.getMainListingParams();

            criteria.addFilter(Criteria.multi(
                'OR',
                [
                    ...this.getLocaleCriterias(params.term),
                    ...this.getTermCriteria(params.term),
                ],
            ));

            criteria.addFilter(Criteria.equals('appId', null));

            return criteria;
        },
    },

    methods: {
        getLocaleCriterias(term) {
            if (!term) {
                return [];
            }

            const criteria = [];
            const locales = Locale.getLocaleRegistry();

            locales.forEach((value, key) => {
                criteria.push(Criteria.contains(`config.label.\"${key}\"`, term));
            });

            return criteria;
        },

        getTermCriteria(term) {
            const criteria = [];

            if (term) {
                criteria.push(Criteria.contains('name', term));
            }

            return criteria;
        },
    },
};
