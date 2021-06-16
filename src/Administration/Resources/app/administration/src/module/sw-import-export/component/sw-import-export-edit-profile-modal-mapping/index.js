import template from './sw-import-export-edit-profile-modal-mapping.html.twig';
import './sw-import-export-edit-profile-modal-mapping.scss';

const { debounce, createId } = Shopware.Utils;
const Criteria = Shopware.Data.Criteria;

/**
 * @private
 */
Shopware.Component.register('sw-import-export-edit-profile-modal-mapping', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Shopware.Mixin.getByName('notification'),
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
            searchTerm: null,
            mappings: [],
            currencies: [],
            languages: [],
            addMappingEnabled: false,
        };
    },

    computed: {
        languageRepository() {
            return this.repositoryFactory.create('language');
        },

        currencyRepository() {
            return this.repositoryFactory.create('currency');
        },

        languageCriteria() {
            const criteria = new Criteria(1, 500);
            criteria.addAssociation('locale');
            return criteria;
        },

        currencyCriteria() {
            return new Criteria(1, 500);
        },

        mappingColumns() {
            return [
                {
                    property: 'csvName',
                    label: 'sw-import-export.profile.mapping.fileValueLabel',
                    allowResize: true,
                    primary: true,
                },
                {
                    property: 'entry',
                    label: 'sw-import-export.profile.mapping.entityLabel',
                    allowResize: true,
                    width: '300px',
                },
            ];
        },
    },

    watch: {
        profile: {
            handler(profile) {
                this.toggleAddMappingActionState(profile.sourceEntity);
            },
            deep: true,
        },
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            this.toggleAddMappingActionState(this.profile.sourceEntity);

            this.languageRepository.search(this.languageCriteria).then(languages => {
                this.languages = languages;
                this.languages.push({ locale: { code: 'DEFAULT' } });
            });

            this.currencyRepository.search(this.currencyCriteria).then(currencies => {
                this.currencies = currencies;
                this.currencies.push({ isoCode: 'DEFAULT' });
            });

            this.loadMappings();
        },

        toggleAddMappingActionState(sourceEntity) {
            this.addMappingEnabled = !!sourceEntity;
        },

        onDeleteMapping(id) {
            this.profile.mapping = this.profile.mapping.filter((mapping) => {
                return mapping.id !== id;
            });

            this.loadMappings();
        },

        loadMappings() {
            if (this.searchTerm) {
                const searchTerm = this.searchTerm.toLowerCase();
                this.mappings = this.profile.mapping.filter(mapping => {
                    const key = mapping.key.toLowerCase();
                    const mappedKey = mapping.mappedKey.toLowerCase();
                    return !!(key.includes(searchTerm) || mappedKey.includes(searchTerm));
                });
                return;
            }

            this.mappings = [];

            this.profile.mapping.forEach((mapping) => {
                if (!mapping.id) {
                    mapping.id = createId();
                }
                this.mappings.push(mapping);
            });
        },

        onAddMapping() {
            if (!this.profile.sourceEntity) {
                return;
            }

            this.profile.mapping.unshift({ id: createId(), key: '', mappedKey: '' });

            this.loadMappings();
        },

        onSearch() {
            this.debouncedSearch();
        },

        debouncedSearch: debounce(function updateSearchTerm() {
            this.loadMappings();
        }, 100),
    },
});
