/**
 * @sw-package framework
 */

import template from './sw-shortcut-overview-item.html.twig';
import './sw-shortcut-overview-item.scss';

function createKeyLabel(label, ariaLabel = label) {
    return {
        label,
        ariaLabel,
    };
}

const keyLabels = {
    Mac: {
        ALT: createKeyLabel('⌥', 'Option'),
        OPTION: createKeyLabel('⌥', 'Option'),
        CONTROL: createKeyLabel('⌃', 'Control'),
        CTRL: createKeyLabel('⌃', 'Control'),
        CMD: createKeyLabel('⌘', 'Command'),
        COMMAND: createKeyLabel('⌘', 'Command'),
        META: createKeyLabel('⌘', 'Command'),
        SHIFT: createKeyLabel('⇧', 'Shift'),
        TAB: createKeyLabel('⇥ Tab', 'Tab'),
        ESC: createKeyLabel('Esc', 'Escape'),
        ESCAPE: createKeyLabel('Esc', 'Escape'),
    },
    Windows: {
        ALT: createKeyLabel('Alt'),
        CONTROL: createKeyLabel('Ctrl', 'Control'),
        CTRL: createKeyLabel('Ctrl', 'Control'),
        CMD: createKeyLabel('⊞', 'Windows'),
        COMMAND: createKeyLabel('⊞', 'Windows'),
        META: createKeyLabel('⊞', 'Windows'),
        WIN: createKeyLabel('⊞', 'Windows'),
        WINDOWS: createKeyLabel('⊞', 'Windows'),
        SHIFT: createKeyLabel('Shift'),
        TAB: createKeyLabel('⇥ Tab', 'Tab'),
        ESC: createKeyLabel('Esc', 'Escape'),
        ESCAPE: createKeyLabel('Esc', 'Escape'),
    },
    Linux: {
        ALT: createKeyLabel('Alt'),
        CONTROL: createKeyLabel('Ctrl', 'Control'),
        CTRL: createKeyLabel('Ctrl', 'Control'),
        CMD: createKeyLabel('Super'),
        COMMAND: createKeyLabel('Super'),
        META: createKeyLabel('Super'),
        SHIFT: createKeyLabel('Shift'),
        TAB: createKeyLabel('⇥ Tab', 'Tab'),
        ESC: createKeyLabel('Esc', 'Escape'),
        ESCAPE: createKeyLabel('Esc', 'Escape'),
    },
};

/**
 * @private
 */
export default {
    template,

    inject: ['acl'],

    props: {
        title: {
            type: String,
            required: true,
        },
        content: {
            type: String,
            required: true,
        },
        privilege: {
            type: String,
            required: false,
            default: null,
        },
    },

    computed: {
        showItem() {
            return this.acl.can(this.privilege);
        },

        platform() {
            const platform = this.$device?.getPlatform?.() ?? window.navigator.platform;

            if (platform.includes('Mac')) {
                return 'Mac';
            }

            if (platform.includes('Win')) {
                return 'Windows';
            }

            return 'Linux';
        },

        keys() {
            return this.content
                .split(' ')
                .flatMap((key) => key.split('-'))
                .filter(Boolean)
                .map((key) => this.formatKey(key));
        },
    },

    methods: {
        formatKey(key) {
            const normalizedKey = key.trim();
            const upperCaseKey = normalizedKey.toUpperCase();
            const label = keyLabels[this.platform]?.[upperCaseKey];

            if (label) {
                return label;
            }

            return createKeyLabel(normalizedKey.length === 1 ? upperCaseKey : normalizedKey);
        },
    },
};
