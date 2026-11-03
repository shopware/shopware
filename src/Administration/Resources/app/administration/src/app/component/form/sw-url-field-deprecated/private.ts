import { definePrivateComponent } from 'src/core/public-component';

/**
 * @sw-package framework
 *
 * The component is registered by its name for the Administration only: it never reaches extensions.
 * The lists hold everything it offered before it became a mini-module and what it inherits from sw-text-field-deprecated,
 * because Component.override inside the Administration could reach all of it. The files in `./src` are private to this module.
 */
/** @private */
export default definePrivateComponent({
    name: 'sw-url-field-deprecated',
    extends: 'sw-text-field-deprecated',
    load: () => import('./src/sw-url-field-deprecated'),
    public: {
        props: [
            'error',
            'omitUrlHash',
            'omitUrlSearch',
            'addTrailingSlash',
            'value',
            'placeholder',
            'copyable',
            'copyableTooltip',
            'idSuffix',
            'ariaLabel',
        ],
        emits: [
            'update:value',
            'inheritance-restore',
            'inheritance-remove',
        ],
        slots: [
            'prefix',
            'suffix',
            'label',
            'hint',
        ],
        blocks: [
            'sw_text_field',
        ],
        computed: [
            'prefixClass',
            'urlPrefix',
            'url',
            'combinedError',
            'unicodeUriFilter',
            'hasPrefix',
            'hasSuffix',
            'filteredInputAttributes',
        ],
        methods: [
            'createdComponent',
            'onBlur',
            'checkInput',
            'handleEmptyUrl',
            'validateCurrentValue',
            'changeMode',
            'getURLInstance',
            'getSSLMode',
            'setInvalidUrlError',
            'onChange',
            'onInput',
            'restoreInheritance',
            'createInputId',
        ],
    },
});
