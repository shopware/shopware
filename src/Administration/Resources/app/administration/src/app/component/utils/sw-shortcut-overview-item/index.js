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
    mac: {
        alt: createKeyLabel('⌥', 'Option'),
        option: createKeyLabel('⌥', 'Option'),
        control: createKeyLabel('⌃', 'Control'),
        ctrl: createKeyLabel('⌃', 'Control'),
        cmd: createKeyLabel('⌘', 'Command'),
        command: createKeyLabel('⌘', 'Command'),
        meta: createKeyLabel('⌘', 'Command'),
        shift: createKeyLabel('⇧', 'Shift'),
        tab: createKeyLabel('⇥ Tab', 'Tab'),
        esc: createKeyLabel('Esc', 'Escape'),
        escape: createKeyLabel('Esc', 'Escape'),
    },
    windows: {
        alt: createKeyLabel('Alt'),
        control: createKeyLabel('Ctrl', 'Control'),
        ctrl: createKeyLabel('Ctrl', 'Control'),
        cmd: createKeyLabel('⊞', 'Windows'),
        command: createKeyLabel('⊞', 'Windows'),
        meta: createKeyLabel('⊞', 'Windows'),
        win: createKeyLabel('⊞', 'Windows'),
        windows: createKeyLabel('⊞', 'Windows'),
        shift: createKeyLabel('Shift'),
        tab: createKeyLabel('⇥ Tab', 'Tab'),
        esc: createKeyLabel('Esc', 'Escape'),
        escape: createKeyLabel('Esc', 'Escape'),
    },
    linux: {
        alt: createKeyLabel('Alt'),
        control: createKeyLabel('Ctrl', 'Control'),
        ctrl: createKeyLabel('Ctrl', 'Control'),
        cmd: createKeyLabel('Super'),
        command: createKeyLabel('Super'),
        meta: createKeyLabel('Super'),
        shift: createKeyLabel('Shift'),
        tab: createKeyLabel('⇥ Tab', 'Tab'),
        esc: createKeyLabel('Esc', 'Escape'),
        escape: createKeyLabel('Esc', 'Escape'),
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
                return 'mac';
            }

            if (platform.includes('Win')) {
                return 'windows';
            }

            return 'linux';
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
            const lowerCaseKey = normalizedKey.toLowerCase();
            const label = keyLabels[this.platform]?.[lowerCaseKey];

            if (label) {
                return label;
            }

            return createKeyLabel(normalizedKey.length === 1 ? normalizedKey.toUpperCase() : normalizedKey);
        },
    },
};
