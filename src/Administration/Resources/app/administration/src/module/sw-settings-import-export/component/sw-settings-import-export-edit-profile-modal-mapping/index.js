import template from './sw-settings-import-export-edit-profile-modal-mapping.html.twig';
import './sw-settings-import-export-edit-profile-modal-mapping.scss';

Shopware.Component.register('sw-settings-import-export-edit-profile-modal-mapping', {
    template,

    inject: [],

    props: {
        profile: {
            type: Object,
            required: false,
            default: false
        }
    },

    data() {
        return {};
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

    created() {},

    methods: {
        onDeleteMapping(key) {
            this.profile.mapping = this.profile.mapping.filter((mapping) => {
                return mapping.key !== key;
            });
        }
    }
});
