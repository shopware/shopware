Shopware.Mixin.register('media-sidebar-modal-mixin', {

    inject: ['mediaService', 'acl'],

    data() {
        return {
            showModalReplace: false,
            showModalDelete: false,
            showFolderSettings: false,
            showFolderDissolve: false,
            showModalMove: false,
        };
    },

    methods: {
        openModalReplace() {
            if (!this.acl.can('media.editor')) {
                return;
            }
            this.showModalReplace = true;
        },

        closeModalReplace() {
            this.showModalReplace = false;
        },

        openModalDelete() {
            if (!this.acl.can('media.deleter')) {
                return;
            }

            this.showModalDelete = true;
        },

        closeModalDelete() {
            this.showModalDelete = false;
        },

        openFolderSettings() {
            this.showFolderSettings = true;
        },

        closeFolderSettings() {
            this.showFolderSettings = false;
        },

        openFolderDissolve() {
            if (!this.acl.can('media.editor')) {
                return;
            }

            this.showFolderDissolve = true;
        },

        closeFolderDissolve() {
            this.showFolderDissolve = false;
        },

        openModalMove() {
            if (!this.acl.can('media.editor')) {
                return;
            }

            this.showModalMove = true;
        },

        closeModalMove() {
            this.showModalMove = false;
        },

        deleteSelectedItems(ids) {
            this.closeModalDelete();

            this.$nextTick(() => {
                this.$emit('media-sidebar-items-delete', ids);
            });
        },

        onFolderDissolved(ids) {
            this.closeFolderDissolve();

            this.$nextTick(() => {
                this.$emit('media-sidebar-folder-items-dissolve', ids);
            });
        },

        onFolderMoved(ids) {
            this.closeModalMove();

            this.$nextTick(() => {
                this.$emit('media-sidebar-items-move', ids);
            });
        },
    },
});
