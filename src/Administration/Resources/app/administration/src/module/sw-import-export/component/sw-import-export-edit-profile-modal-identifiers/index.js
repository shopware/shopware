/**
 * @package system-settings
 */
import template from './sw-import-export-edit-profile-modal-identifiers.html.twig';
import './sw-import-export-edit-profile-modal-identifiers.scss';

const Criteria = Shopware.Data.Criteria;

/**
 * @private
 */
export default {
    template,

    inject: [
        'repositoryFactory',
        'importExportUpdateByMapping',
    ],

    props: {
        profile: {
            type: Object,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            currencies: [],
            languages: [],
            customFieldSets: [],
        };
    },

    computed: {
        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        customFieldSetRepository() {
            return this.repositoryFactory.create('custom_field_set');
        },

        languageCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addAssociation('locale');

            return criteria;
        },

        currencyCriteria() {
            return new Criteria(1, 500);
        },

        customFieldSetCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addAssociation('relations');
            criteria.addAssociation('customFields');

            return criteria;
        },

        identifierColumns() {
            return [
                {
                    property: 'identifierName',
                    label: 'sw-import-export.profile.identifiers.identifierNameLabel',
                    allowResize: false,
                    primary: true,
                },
                {
                    property: 'mapped',
                    label: 'sw-import-export.profile.identifiers.mappedKeyLabel',
                    allowResize: false,
                    width: '100%',
                },
            ];
        },

        identifiers() {
            const identifiers = {};

            if (!this.profile.mapping) {
                return [];
            }

            this.profile.mapping.forEach((mapping) => {
                const { entity, path, relation, name } = this.importExportUpdateByMapping.getEntity(
                    this.profile.sourceEntity,
                    mapping.key,
                );

                if (!entity || relation === 'one_to_many') {
                    return;
                }

                identifiers[entity] = identifiers[entity] ?? {
                    entityName: entity,
                    options: [],
                    selected: this.importExportUpdateByMapping.getSelected(entity, this.profile.updateBy),
                    relation,
                    propertyNames: [],
                };

                const value = path !== '' ? mapping.key.replace(new RegExp(`^(${path}\.)`), '') : mapping.key;

                identifiers[entity].options.push({ label: value, value });

                if (!identifiers[entity].propertyNames.includes(name)) {
                    identifiers[entity].propertyNames.push(name);
                }
            });

            return Object.values(identifiers);
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.languageRepository.search(this.languageCriteria).then(languages => {
                this.languages = languages;
                this.languages.push({ locale: { code: 'DEFAULT' } });
            });

            this.currencyRepository.search(this.currencyCriteria).then(currencies => {
                this.currencies = currencies;
                this.currencies.push({ isoCode: 'DEFAULT' });
            });

            this.customFieldSetRepository.search(this.customFieldSetCriteria).then((customFieldSets) => {
                this.customFieldSets = customFieldSets;
            });
        },

        onChangeIdentifier(mappedKey, entityName) {
            this.importExportUpdateByMapping.updateMapping(this.profile, mappedKey, entityName);
        },
    },
};
