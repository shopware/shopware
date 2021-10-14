import template from './sw-import-export-edit-profile-modal-mapping.html.twig';
import './sw-import-export-edit-profile-modal-mapping.scss';

const { debounce, createId } = Shopware.Utils;
const Criteria = Shopware.Data.Criteria;

/**
 * @private
 */
Shopware.Component.register('sw-import-export-edit-profile-modal-mapping', {
    template,

    inject: [
        'repositoryFactory',
        'feature',
    ],

    mixins: [
        Shopware.Mixin.getByName('notification'),
    ],

    props: {
        profile: {
            type: Object,
            required: false,
            default: null,
        },
        systemRequiredFields: {
            type: Object,
            required: false,
            default() {
                return {};
            },
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
            let columns = [
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

            if (this.feature.isActive('FEATURE_NEXT_8097') && this.profile.type !== 'export') {
                columns = [...columns, {
                    property: 'required',
                    label: 'sw-import-export.profile.mapping.isRequired',
                    allowResize: true,
                    align: 'center',
                },
                {
                    property: 'defaultValue',
                    label: 'sw-import-export.profile.mapping.defaultValue',
                    allowResize: true,
                    width: '300px',
                }];
            }

            if (this.feature.isActive('FEATURE_NEXT_15998') && !this.profile.systemDefault) {
                columns = [...columns, {
                    property: 'position',
                    label: 'sw-import-export.profile.mapping.position',
                    allowResize: false,
                    align: 'center',
                }];
            }

            return columns;
        },

        mappingsExist() {
            return this.profile.mapping.length > 0;
        },

        sortedMappings() {
            const mappings = this.mappings;

            return mappings.sort((firstMapping, secondMapping) => {
                if (firstMapping.position > secondMapping.position) {
                    return 1;
                }

                if (firstMapping.position < secondMapping.position) {
                    return -1;
                }

                return 0;
            });
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

            // update position of all mappings
            this.profile.mapping.forEach(currentMapping => { currentMapping.position += 1; });

            this.profile.mapping.unshift({ id: createId(), key: '', mappedKey: '', position: 0 });

            this.loadMappings();
        },

        onSearch() {
            this.debouncedSearch();
        },

        debouncedSearch: debounce(function updateSearchTerm() {
            this.loadMappings();
        }, 100),

        isDefaultValueCheckboxDisabled() {
            return this.profile.systemDefault;
        },

        isDefaultValueTextFieldDisabled(item) {
            return this.profile.systemDefault || !item.useDefaultValue;
        },

        isRequiredBySystem(item) {
            if (!item || !item.key) {
                return false;
            }

            return this.systemRequiredFields[item.key] !== undefined;
        },

        updateSorting(mapping, index, direction) {
            if (direction === 'up') {
                // return if mapping is the most upper one
                if (index === 0) return;

                const previousMapping = this.sortedMappings[index - 1];
                this.swapItems(previousMapping, mapping);

                return;
            }

            const totalLengthOfMappings = this.sortedMappings.length;
            if (direction === 'down') {
                // return if mapping is the lowest
                if (index === totalLengthOfMappings - 1) return;

                const nextMapping = this.sortedMappings[index + 1];
                this.swapItems(mapping, nextMapping);
            }
        },

        /**
         * first item goes one down and second item goes one up
         * @param firstItem
         * @param secondItems
         */
        swapItems(firstItem, secondItems) {
            const positionOfFirstItem = firstItem.position;

            firstItem.position = secondItems.position;
            secondItems.position = positionOfFirstItem;
        },

        isFirstMapping(item) {
            return item.position === 0;
        },

        isLastMapping(item) {
            const lastPosition = this.profile.mapping.length - 1;

            return item.position === lastPosition;
        },
    },
});
