import { Component, Mixin } from 'src/core/shopware';
import { format } from 'src/core/service/util.service';
import template from './sw-media-folder-info.html.twig';


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
                return value.entityName === 'media_folder';
            }
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
