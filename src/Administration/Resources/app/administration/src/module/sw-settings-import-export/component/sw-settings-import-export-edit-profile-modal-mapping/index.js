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
                    label: 'CSV name', // TODO: add translations
                    allowResize: true,
                    primary: true
                },
                {
                    property: 'entry',
                    label: 'Database entry', // TODO: add translations
                    allowResize: true,
                    width: '250px'
                }
            ];
        }
    },

    created() {},

    methods: {
        onDeleteMapping(id) {
            // TODO: Implement deletion
            console.log('Delete ', id);
        }
    }
});
