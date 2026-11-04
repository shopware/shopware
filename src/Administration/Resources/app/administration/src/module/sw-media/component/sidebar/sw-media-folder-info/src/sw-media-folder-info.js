import template from './sw-media-folder-info.html.twig';
import './sw-media-folder-info.scss';
import {
    aclService,
    date,
    getApiContext,
    mapPropertyErrors,
    mediaSidebarModalMixinMixin,
    repositoryFactoryService,
} from '../dependencies';

/**
 * @sw-package discovery
 *
 * @public
 */
export default {
    template,

    inject: [repositoryFactoryService, aclService],

    emits: ['media-folder-renamed'],

    mixins: [mediaSidebarModalMixinMixin],

    props: {
        mediaFolder: {
            type: Object,
            required: true,
            validator(value) {
                return value.getEntityName() === 'media_folder';
            },
        },

        editable: {
            type: Boolean,
            required: false,
            default: false,
        },
    },

    computed: {
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },

        createdAt() {
            return date(this.mediaFolder.createdAt);
        },

        ...mapPropertyErrors('mediaFolder', ['name']),

        nameItemClasses() {
            return {
                'has--error': !!this.mediaFolderNameError,
            };
        },
    },

    methods: {
        async onChangeFolderName(newName) {
            this.mediaFolder.name = newName;
            await this.mediaFolderRepository.save(this.mediaFolder, getApiContext());
            this.$emit('media-folder-renamed');
        },

        quickActionClasses(disabled) {
            return [
                'sw-media-sidebar__quickaction',
                {
                    'sw-media-sidebar__quickaction--disabled': disabled,
                },
            ];
        },
    },
};
