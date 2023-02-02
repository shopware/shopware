import template from './sw-media-modal-move.html.twig';
import './sw-media-modal-move.scss';

const { Mixin, Context } = Shopware;

/**
 * @status ready
 * @description The <u>sw-media-modal-move</u> component is used to validate the move action.
 * @package content
 * @example-type code-only
 * @component-example
 * <sw-media-modal-move :items-to-move="[items]"></sw-media-modal-move>
 */
// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    template,

    inject: ['repositoryFactory'],

    provide() {
        return {
            filterItems: this.isNotPartOfItemsToMove,
        };
    },

    mixins: [
        Mixin.getByName('notification'),
    ],

    props: {
        itemsToMove: {
            required: true,
            type: Array,
            validator(value) {
                return (value.length > 0);
            },
        },
    },

    data() {
        return {
            targetFolder: null,
            parentFolder: null,
            displayFolder: null,
            displayFolderId: null,
        };
    },

    computed: {
        mediaRepository() {
            return this.repositoryFactory.create('media');
        },

        mediaFolderRepository() {
            return this.repositoryFactory.create('media_folder');
        },

        mediaNameFilter() {
            return (media) => {
                return media.getEntityName() === 'media' ?
                    `${media.fileName}.${media.fileExtension}` :
                    media.name;
            };
        },

        targetFolderId() {
            return this.targetFolder ? this.targetFolder.id : null;
        },

        rootFolderName() {
            return this.$tc('sw-media.index.rootFolderName');
        },

        isMoveDisabled() {
            return this.startFolderId === this.targetFolderId;
        },

        startFolderId() {
            const firstItem = this.itemsToMove[0];
            if (firstItem.getEntityName() === 'media') {
                return firstItem.mediaFolderId;
            }

            return firstItem.parentId;
        },
    },

    watch: {
        displayFolder(newFolder) {
            this.displayFolderId = newFolder.id;
            this.updateParentFolder(newFolder);
        },
    },

    mounted() {
        this.mountedComponent();
    },

    methods: {
        async mountedComponent() {
            this.displayFolder = { id: null, name: this.rootFolderName };
            this.targetFolder = { id: null, name: this.rootFolderName };

            if (this.startFolderId) {
                const folder = await this.mediaFolderRepository.get(this.startFolderId, Context.api);
                this.displayFolder = folder;
                this.targetFolder = folder;
            }
        },

        closeMoveModal() {
            this.$emit('media-move-modal-close');
        },

        isNotPartOfItemsToMove(item) {
            return !this.itemsToMove.some((i) => {
                return i.id === item.id;
            });
        },

        async updateParentFolder(child) {
            if (child.id === null) {
                this.parentFolder = null;
            } else if (child.parentId === null) {
                this.parentFolder = { id: null, name: this.rootFolderName };
            } else {
                this.parentFolder = await this.mediaFolderRepository.get(child.parentId, Context.api);
            }
        },

        onSelection(folder) {
            this.targetFolder = folder;
            // the children aren't always loaded
            if (folder.children) {
                if (folder.children.filter(this.isNotPartOfItemsToMove).length > 0) {
                    this.displayFolder = folder;
                }
                return;
            }

            if (folder.id === null || folder.childCount > 0) {
                this.displayFolder = folder;
            }
        },

        async _moveSelection(item) {
            item.isLoading = true;
            item.parentId = this.targetFolder.id || null;

            try {
                await this.mediaFolderRepository.save(item, Context.api);

                this.createNotificationSuccess({
                    title: this.$root.$tc('global.default.success'),
                    message: this.$root.$tc(
                        'global.sw-media-modal-move.notification.successSingle.message',
                        1,
                        { mediaName: this.mediaNameFilter(item) },
                    ),
                });

                return item.id;
            } catch {
                this.createNotificationError({
                    title: this.$root.$tc('global.default.error'),
                    message: this.$root.$tc(
                        'global.sw-media-modal-move.notification.errorSingle.message',
                        1,
                        { mediaName: this.mediaNameFilter(item) },
                    ),
                });

                return null;
            } finally {
                item.isLoading = false;
            }
        },

        async moveSelection() {
            const movedIds = [];

            try {
                const folders = this.itemsToMove.filter((item) => {
                    return item.getEntityName() === 'media_folder';
                });

                const media = this.itemsToMove.filter((item) => {
                    return item.getEntityName() === 'media';
                });

                await Promise.all(folders.map(async (folder) => {
                    await this._moveSelection(folder);
                }));

                await Promise.all(media.map(async (mediaItem) => {
                    const item = mediaItem;
                    item.mediaFolderId = this.targetFolder.id || null;
                    movedIds.push(await this.mediaRepository.save(item, Context.api));
                }));

                this.createNotificationSuccess({
                    title: this.$root.$tc('global.default.success'),
                    message: this.$root.$tc('global.sw-media-modal-move.notification.successOverall.message'),
                });

                this.$emit(
                    'media-move-modal-items-move',
                    movedIds,
                );
            } catch {
                this.createNotificationError({
                    title: this.$root.$tc('global.default.error'),
                    message: this.$root.$tc('global.sw-media-modal-move.notification.errorOverall.message'),
                });
            }
        },
    },
};
