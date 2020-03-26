import template from './sw-settings-import-export-edit-profile-modal-mapping.html.twig';
import './sw-settings-import-export-edit-profile-modal-mapping.scss';

const { debounce } = Shopware.Utils;
const Criteria = Shopware.Data.Criteria;

Shopware.Component.register('sw-settings-import-export-edit-profile-modal-mapping', {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Shopware.Mixin.getByName('notification')
    ],

    props: {
        profile: {
            type: Object,
            required: false,
            default: false
        }
    },

    data() {
        return {
            searchTerm: null,
            mappings: this.profile.mapping,
            currencies: [],
            languages: [],
            addMappingEnabled: false
        };
    },

    watch: {
        profile: {
            handler(profile) {
                this.toggleAddMappingActionState(profile.sourceEntity);
            },
            deep: true
        }
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
                    label: 'sw-settings-import-export.profile.mapping.fileValueLabel',
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'entry',
                    label: 'sw-settings-import-export.profile.mapping.entityLabel',
                    allowResize: true,
                    width: '250px'
                }
            ];
        }
    },

    created() {
        this.createdComponent();
        this.toggleAddMappingActionState(this.profile.sourceEntity);
    },

    methods: {
        createdComponent() {
            this.languageRepository.search(this.languageCriteria, Shopware.Context.api).then(languages => {
                this.languages = languages;
                this.languages.push({ locale: { code: 'DEFAULT' } });
            });

            this.currencyRepository.search(this.currencyCriteria, Shopware.Context.api).then(currencies => {
                this.currencies = currencies;
                this.currencies.push({ isoCode: 'DEFAULT' });
            });
        },

        toggleAddMappingActionState(sourceEntity) {
            this.addMappingEnabled = !!sourceEntity;
        },

        onDeleteMapping(index) {
            this.profile.mapping.splice(index, 1);

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

            this.mappings = this.profile.mapping;
        },

        onAddMapping() {
            if (!this.profile.sourceEntity) {
                return;
            }

            this.profile.mapping.unshift({ key: '', mappedKey: '' });
        },

        onSearch() {
            this.debouncedSearch();
        },

        debouncedSearch: debounce(function updateSearchTerm() {
            this.loadMappings();
        }, 100)
    }
});
