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
    name: 'sw-language-switch',
    load: () => import('./src/sw-language-switch'),
    public: {
        props: [
            'disabled',
            'changeGlobalLanguage',
            'abortChangeFunction',
            'saveChangesFunction',
            'savePermission',
            'allowEdit',
        ],
        emits: [
            'on-change',
        ],
        blocks: [
            'sw_language_switch',
            'sw_language_switch_modal',
            'sw_language_switch_message',
            'sw_language_switch_footer',
            'sw_language_switch_footer_button_close',
            'sw_language_switch_footer_button_revert',
            'sw_language_switch_footer_button_save',
            'sw_language_switch_select',
        ],
        computed: [
            'languageCriteria',
            'languageCacheKey',
        ],
        methods: [
            'createdComponent',
            'destroyedComponent',
            'onInput',
            'checkAbort',
            'emitChange',
            'onCloseChangesModal',
            'onClickSaveChanges',
            'onClickRevertUnsavedChanges',
            'changeToNewLanguage',
        ],
    },
});
