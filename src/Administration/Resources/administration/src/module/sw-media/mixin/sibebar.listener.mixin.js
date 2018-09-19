import { warn } from 'src/core/service/utils/debug.utils';

const mediaSidebarListener = {
    computed: {
        mediaSidebarListener() {
            return {
                'sw-media-sidebar-move-batch': this.handleSidebarMoveBatchRequest,
                'sw-media-sidebar-remove-batch': this.handleSidebarRemoveBatchRequest,
                'sw-media-sidebar-quickaction-move-item': this.handleSidebarMoveItem,
                'sw-media-sidebar-quickaction-remove-item': this.handleSidebarRemoveItem
            };
        }
    },

    methods: {
        handleSidebarMoveBatchRequest() {
            warn('handleSidebarMoveBatchRequest', 'Handler must be overriden in component');
        },

        handleSidebarRemoveBatchRequest() {
            warn('handleSidebarRemoveBatchRequest', 'Handler must be overriden in component');
        },

        handleSidebarMoveItem() {
            warn('handleSidebarMoveItem', 'Handler must be overriden in component');
        },

        handleSidebarRemoveItem() {
            warn('handleSidebarRemoveItem', 'Handler must be overriden in component');
        }
    }
};

export default mediaSidebarListener;
