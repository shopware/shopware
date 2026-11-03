import { definePublicComponent } from 'src/core/public-component';

/**
 * @sw-package discovery
 *
 * This file is the public API, also for extensions. The files in `./src` are private.
 * The lists hold everything the component offered before it became a mini-module,
 * because Component.override could reach all of it. Shrink them only through the deprecation process.
 */
/** @public */
export default definePublicComponent({
    name: 'sw-media-field',
    load: () => import('./src/sw-media-field'),
    public: {
        props: [
            'value',
            'disabled',
            'label',
            'defaultFolder',
            'fileAccept',
        ],
        emits: [
            'update:value',
        ],
        slots: [
            'label',
        ],
        blocks: [
            'sw_media_field',
            'sw_media_field_label',
            'sw_media_field_input',
            'sw_media_field_preview',
            'sw_media_field_toggle_button',
            'sw_media_field_media_icon',
            'sw_media_field_media_popover',
            'sw_media_field_action_bar',
            'sw_media_field_action_bar_button_toggle',
            'sw_media_field_action_bar_button_unlink',
            'sw_media_field_upload_component',
            'sw_media_field_search_field',
            'sw_media_field_media_list',
            'sw_media_field_suggestion_preview',
        ],
        computed: [
            'mediaId',
            'mediaRepository',
            'popoverConfig',
            'mediaFieldClasses',
            'toggleButtonLabel',
            'suggestionCriteria',
        ],
        methods: [
            'createdComponent',
            'onSearchTermChange',
            'fetchItem',
            'fetchSuggestions',
            'onTogglePicker',
            'mediaItemChanged',
            'removeLink',
            'computePickerPositionAndStyle',
            'toggleUploadField',
            'exposeNewId',
            'showLabel',
            'onPageChange',
        ],
    },
});
