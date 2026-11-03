import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-file-input',
    load: () => import('./src/sw-file-input'),
    public: {
        props: [
            'maxFileSize',
            'allowedMimeTypes',
            'allowedFileExtensions',
            'label',
            'value',
            'disabled',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            'caption-label',
        ],
        blocks: [
            'sw_file_input',
            'sw_file_input_regular',
            'sw_file_input_regular_header',
            'sw_file_input_regular_header_label',
            'sw_file_input_regular_drop_zone',
            'sw_file_input_caption',
            'sw_file_input_actions',
            'sw_file_input_regular_choose_button',
            'sw_file_input_hidden_form',
        ],
        computed: [
            'id',
            'isDragActiveClass',
        ],
        methods: [
            'mountedComponent',
            'beforeUnmountComponent',
            'onChooseButtonClick',
            'onRemoveIconClick',
            'onFileInputChange',
            'setSelectedFile',
            'checkFileSize',
            'checkFileType',
            'checkFileExtension',
            'onDragEnter',
            'onDragLeave',
            'stopEventPropagation',
            'onDrop',
        ],
    },
});
