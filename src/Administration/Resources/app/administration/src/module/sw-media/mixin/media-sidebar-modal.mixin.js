const { Mixin } = Shopware;

Mixin.register('media-sidebar-modal-mixin', {

    data() {
        return {
            showModalReplace: false,
            showModalDelete: false,
            showFolderSettings: false,
            showFolderDissolve: false,
            showModalMove: false
        };
    },

    methods: {
        openModalReplace() {
            this.showModalReplace = true;
        },

        closeModalReplace() {
            this.showModalReplace = false;
        },

        openModalDelete() {
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
            this.showFolderDissolve = true;
        },

        closeFolderDissolve() {
            this.showFolderDissolve = false;
        },

        openModalMove() {
            this.showModalMove = true;
        },

        closeModalMove() {
            this.showModalMove = false;
        },

        deleteSelectedItems(deletePromise) {
            this.closeModalDelete();
            deletePromise.then((ids) => {
                this.$emit('media-sidebar-items-delete', ids);
            });
        },

        hideSelectedItems(deletePromise) {
            this.closeModalHide();
            deletePromise.then((ids) => {
                this.$emit('media-sidebar-items-hide', ids);
            });
        },

        unhideSelectedItems(deletePromise) {
            this.closeModalUnhide();
            deletePromise.then((ids) => {
                this.$emit('media-sidebar-items-unhide', ids);
            });
        },

        onFolderDissolved(dissolvePromise) {
            this.closeFolderDissolve();
            dissolvePromise.then((ids) => {
                this.$emit('media-sidebar-folder-items-dissolve', ids);
            });
        },

        onFolderMoved(movePromise) {
            this.closeModalMove();
            movePromise.then((ids) => {
                this.$emit('media-sidebar-items-move', ids);
            });
        }
    }
});
