import template from './sw-settings-import-export-edit-profile-modal-mapping.html.twig';
import './sw-settings-import-export-edit-profile-modal-mapping.scss';

const { debounce } = Shopware.Utils;

Shopware.Component.register('sw-settings-import-export-edit-profile-modal-mapping', {
    template,

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

    created() {
        this.toggleAddMappingActionState(this.profile.sourceEntity);
    },

    computed: {
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

    methods: {
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
