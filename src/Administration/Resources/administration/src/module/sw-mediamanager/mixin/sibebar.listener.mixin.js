import { warn } from 'src/core/service/utils/debug.utils';

const mediamanagerSidebarListener = {
    computed: {
        mediamanagerSidebarListener() {
            return {
                'sw-mediamanager-sidebar-move-batch': this.handleSidebarMoveBatchRequest,
                'sw-mediamanager-sidebar-remove-batch': this.handleSidebarRemoveBatchRequest,
                'sw-mediamanager-sidebar-quickaction-replace-item': this.handleSidebarReplaceItem,
                'sw-mediamanager-sidebar-quickaction-move-item': this.handleSidebarMoveItem,
                'sw-mediamanager-sidebar-quickaction-remove-item': this.handleSidebarRemoveItem
            };
        }
    },

    methods: {
        handleSidebarMoveBatchRequest() {
            warn('handleMediaGridSelectionRemoved', 'Handler must be overriden in component');
        },
        handleSidebarRemoveBatchRequest() {
            warn('handleSidebarRemoveBatchRequest', 'Handler must be overriden in component');
        },
        handleSidebarReplaceItem() {
            warn('handleSidebarReplaceItem', 'Handler must be overriden in component');
        },
        handleSidebarMoveItem() {
            warn('handleSidebarMoveItem', 'Handler must be overriden in component');
        },
        handleSidebarRemoveItem() {
            warn('handleSidebarRemoveItem', 'Handler must be overriden in component');
        }
    }
};

export default mediamanagerSidebarListener;
