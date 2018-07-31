import { warn } from 'src/core/service/utils/debug.utils';

const mediamanagerMediaGridListener = {
    computed: {
        mediamanagerMediaGridListener() {
            return {
                'sw-media-grid-selection-clear': this.handleMediaGridSelectionRemoved,
                'sw-media-grid-item-selection-add': this.handleMediaGridItemSelected,
                'sw-media-grid-item-selection-remove': this.handleMediaGridItemUnselected
            };
        }
    },

    methods: {
        handleMediaGridSelectionRemoved() {
            warn('handleMediaGridSelectionRemoved', 'Handler must be overriden in component');
        },
        handleMediaGridItemSelected() {
            warn('handleMediaGridItemSelected', 'Handler must be overriden in component');
        },
        handleMediaGridItemUnselected() {
            warn('handleMediaGridItemSelected', 'Handler must be overriden in component');
        }
    }
};

export default mediamanagerMediaGridListener;
