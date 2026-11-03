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
    name: 'sw-code-editor',
    load: () => import('./src/sw-code-editor'),
    public: {
        props: [
            'value',
            'label',
            'completerFunction',
            'editorConfig',
            'completionMode',
            'mode',
            'softWraps',
            'setFocus',
            'disabled',
            'required',
            'sanitizeInfoWarn',
            'sanitizeInput',
            'sanitizeFieldName',
            'error',
            'placeholder',
        ],
        emits: [
            'mounted',
            'update:value',
            'blur',
        ],
        blocks: [
            'sw_code_editor',
            'sw_text_editor_label',
            'sw_code_editor_editor',
            'sw_code_editor_sanitize_info',
            'sw_code_editor_sanitize_info_content',
            'sw_code_editor_sanitize_info_content_sanitized',
            'sw_code_editor_sanitize_info_content_hint',
        ],
        computed: [
            'aceConfig',
            'classes',
            'enableHtmlSanitizer',
            'attrsWithoutClass',
        ],
        methods: [
            'mountedComponent',
            'beforeUnmountedComponent',
            'destroyedComponent',
            'onInput',
            'onBlur',
            'sanitizeEditorInput',
            'defineAutocompletion',
        ],
    },
});
