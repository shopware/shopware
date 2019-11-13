import template from './sw-media-folder-info.html.twig';

const { Component, Mixin } = Shopware;
const format = Shopware.Utils.format;

Component.register('sw-media-folder-info', {
    template,

    mixins: [
        Mixin.getByName('media-sidebar-modal-mixin')
    ],

    props: {
        mediaFolder: {
            type: Object,
            required: true,
            validator(value) {
                return value.getEntityName() === 'media_folder';
            }
        },

        editable: {
            type: Boolean,
            required: false,
            default: false
        }
    },

    computed: {
        createdAt() {
            return format.date(this.mediaFolder.createdAt);
        }
    },

    methods: {
        onChangeFolderName(newName) {
            this.mediaFolder.name = newName;
            this.mediaFolder.save();
        }
    }
});
