import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-duplicated-media-v2',
    load: () => import('./src/sw-duplicated-media-v2'),
    public: {
        blocks: [
            'sw_duplicated_media_v2',
            'sw_duplicated_media_v2_body',
            'sw_duplicated_media_v2_body_description',
            'sw_duplicated_media_v2_body_preview',
            'sw_duplicated_media_v2_body_preview_label_old_file',
            'sw_duplicated_media_v2_body_preview_label_new_file',
            'sw_duplicated_media_v2_body_preview_new_media',
            'sw_media_duplicated_media_target_preview',
            'sw_media_duplicated_media_target_name',
            'sw_media_duplicated_media_target_details',
            'sw_duplicated_media_v2_body_preview_separator',
            'sw_duplicated_media_v2_body_preview_old_media',
            'sw_duplicated_media_v2_body_options',
            'sw_duplicated_media_v2_footer',
            'sw_duplicated_media_v2_save_selection',
            'sw_duplicated_media_v2_cancel_button',
            'sw_duplicated_media_v2_upload_button',
        ],
        computed: [
            'presignedSupported',
            'mediaRepository',
            'additionalErrorCount',
            'hasAdditionalErrors',
            'currentTask',
            'buttonLabel',
            'dateFilter',
            'fileSizeFilter',
            'currentTaskDetails',
            'showModal',
            'isWorkingOnMultipleTasks',
            'options',
        ],
        methods: [
            'createdComponent',
            'beforeDestroyComponent',
            'loadDefaultOption',
            'saveDefaultOption',
            'handleMediaServiceUploadEvent',
            'isDuplicatedNameError',
            'updatePreviewData',
            'solveDuplicate',
            'renameFile',
            'skipAll',
            'skipCurrentFile',
            'skipFile',
            'replaceFile',
            'presignedUpload',
            'emitUploadFinished',
            'keepFile',
        ],
    },
});
