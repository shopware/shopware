import template from './sw-media-folder-content.html.twig';
import './sw-media-folder-content.scss';
import {
    defaultFolderIconNames,
    folderIconColors,
    getFolderColorFamily,
    getFolderThumbnailName,
    normalizeIconName,
} from '../media-folder-visuals.helper';

const { Application, Context } = Shopware;
const { Criteria } = Shopware.Data;
const { warn } = Shopware.Utils.debug;

/**
 * @sw-package discovery
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: [
        'filterItems',
        'repositoryFactory',
    ],

    emits: ['selected'],

    props: {
        startFolderId: {
            type: String,
            required: false,
            default: null,
        },

        selectedId: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            subFolders: [],
            parentFolder: null,
            folderVisuals: {},
        };
    },

    computed: {
        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },

        mediaDefaultFolderRepository() {
            return this.repositoryFactory.create('media_default_folder');
        },

        moduleFactory() {
            return Application.getContainer('factory').module;
        },

        assetFilter() {
            return Shopware.Filter.getByName('asset');
        },
    },

    watch: {
        startFolderId() {
            this.getSubFolders(this.startFolderId);
            this.fetchParentFolder(this.startFolderId);
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        mountedComponent() {
            this.getSubFolders(this.startFolderId);
            this.fetchParentFolder(this.startFolderId);
        },

        async getSubFolders(parentId) {
            const criteria = new Criteria(1, 50)
                .addFilter(Criteria.equals('parentId', parentId))
                .addAssociation('children')
                .addAssociation('defaultFolder')
                .addSorting(Criteria.sort('name', 'asc'));

            const searchResult = await this.mediaFolderRepository.search(criteria, Context.api);
            this.subFolders = searchResult.filter(this.filterItems);
            this.updateFolderVisuals();
        },

        getChildCount(folder) {
            return folder.children.filter(this.filterItems).length;
        },

        async fetchParentFolder(folderId) {
            if (folderId !== null) {
                const folder = await this.mediaFolderRepository.get(folderId, Context.api);
                this.updateParentFolder(folder);
            } else {
                this.parentFolder = null;
            }
        },

        async updateParentFolder(child) {
            if (child.id === null) {
                this.parentFolder = null;
            } else if (child.parentId === null) {
                this.parentFolder = {
                    id: null,
                    name: this.$t('sw-media.index.rootFolderName'),
                };
            } else {
                this.parentFolder = await this.mediaFolderRepository.get(child.parentId, Context.api);
            }
        },

        emitInput(folder) {
            this.$emit('selected', folder);
        },

        isAiGeneratedFolder(folder) {
            return folder?.name === 'AI-generated';
        },

        getFolderVisual(folder) {
            if (this.isAiGeneratedFolder(folder)) {
                return {
                    thumbnailName: getFolderThumbnailName('ai_generated', defaultFolderIconNames.ai_generated),
                    icon: {
                        name: defaultFolderIconNames.ai_generated,
                        color: folderIconColors.blue,
                    },
                };
            }

            return (
                this.folderVisuals[folder.id] ?? {
                    thumbnailName: 'multicolor-folder-thumbnail',
                    icon: null,
                }
            );
        },

        async updateFolderVisuals() {
            const visuals = await Promise.all(
                this.subFolders.map(async (folder) => {
                    return [
                        folder.id,
                        await this.resolveFolderVisual(folder),
                    ];
                }),
            );

            this.folderVisuals = Object.fromEntries(visuals);
        },

        async resolveFolderVisual(folder) {
            if (!folder.defaultFolderId) {
                return {
                    thumbnailName: 'multicolor-folder-thumbnail',
                    icon: null,
                };
            }

            const defaultFolder =
                folder.defaultFolder ?? (await this.mediaDefaultFolderRepository.get(folder.defaultFolderId, Context.api));

            if (!defaultFolder) {
                return {
                    thumbnailName: 'multicolor-folder-thumbnail',
                    icon: null,
                };
            }

            const module = this.moduleFactory.getModuleByEntityName(defaultFolder.entity);

            if (!module) {
                warn('Missing module for default folder entity', defaultFolder.entity);
            }

            const iconName = normalizeIconName(defaultFolderIconNames[defaultFolder.entity] ?? module?.manifest?.icon ?? '');

            return {
                thumbnailName: getFolderThumbnailName(defaultFolder.entity, iconName),
                icon: iconName
                    ? {
                          name: iconName,
                          color: folderIconColors[getFolderColorFamily(defaultFolder.entity, iconName)],
                      }
                    : null,
            };
        },
    },
};
