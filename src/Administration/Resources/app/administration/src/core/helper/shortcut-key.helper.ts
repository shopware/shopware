/**
 * @sw-package framework
 */

/**
 * @private
 */
export const PLATFORM = {
    MAC: 'mac',
    WINDOWS: 'windows',
    LINUX: 'linux',
} as const;

const KEY_SYMBOL = {
    OPTION: '⌥',
    CONTROL: '⌃',
    COMMAND: '⌘',
    SHIFT: '⇧',
    WINDOWS: '⊞',
    TAB: '⇥ Tab',
} as const;

/**
 * @private
 */
export type ShortcutKeyLabel = {
    label: string;
    ariaLabel: string;
};

/**
 * @private
 */
export type KeyboardPlatform = (typeof PLATFORM)[keyof typeof PLATFORM];

const keyLabels: Record<KeyboardPlatform, Record<string, ShortcutKeyLabel>> = {
    [PLATFORM.MAC]: {
        alt: createKeyLabel(KEY_SYMBOL.OPTION, 'Option'),
        option: createKeyLabel(KEY_SYMBOL.OPTION, 'Option'),
        control: createKeyLabel(KEY_SYMBOL.CONTROL, 'Control'),
        ctrl: createKeyLabel(KEY_SYMBOL.CONTROL, 'Control'),
        cmd: createKeyLabel(KEY_SYMBOL.COMMAND, 'Command'),
        command: createKeyLabel(KEY_SYMBOL.COMMAND, 'Command'),
        meta: createKeyLabel(KEY_SYMBOL.COMMAND, 'Command'),
        shift: createKeyLabel(KEY_SYMBOL.SHIFT, 'Shift'),
        tab: createKeyLabel(KEY_SYMBOL.TAB, 'Tab'),
        esc: createKeyLabel('Esc', 'Escape'),
        escape: createKeyLabel('Esc', 'Escape'),
    },
    [PLATFORM.WINDOWS]: {
        alt: createKeyLabel('Alt'),
        control: createKeyLabel('Ctrl', 'Control'),
        ctrl: createKeyLabel('Ctrl', 'Control'),
        cmd: createKeyLabel(KEY_SYMBOL.WINDOWS, 'Windows'),
        command: createKeyLabel(KEY_SYMBOL.WINDOWS, 'Windows'),
        meta: createKeyLabel(KEY_SYMBOL.WINDOWS, 'Windows'),
        win: createKeyLabel(KEY_SYMBOL.WINDOWS, 'Windows'),
        windows: createKeyLabel(KEY_SYMBOL.WINDOWS, 'Windows'),
        shift: createKeyLabel('Shift'),
        tab: createKeyLabel(KEY_SYMBOL.TAB, 'Tab'),
        esc: createKeyLabel('Esc', 'Escape'),
        escape: createKeyLabel('Esc', 'Escape'),
    },
    [PLATFORM.LINUX]: {
        alt: createKeyLabel('Alt'),
        control: createKeyLabel('Ctrl', 'Control'),
        ctrl: createKeyLabel('Ctrl', 'Control'),
        cmd: createKeyLabel('Super'),
        command: createKeyLabel('Super'),
        meta: createKeyLabel('Super'),
        shift: createKeyLabel('Shift'),
        tab: createKeyLabel(KEY_SYMBOL.TAB, 'Tab'),
        esc: createKeyLabel('Esc', 'Escape'),
        escape: createKeyLabel('Esc', 'Escape'),
    },
};

function createKeyLabel(label: string, ariaLabel: string = label): ShortcutKeyLabel {
    return {
        label,
        ariaLabel,
    };
}

/**
 * @private
 */
export function classifyPlatform(rawPlatform: string): KeyboardPlatform {
    if (rawPlatform.includes('Mac')) {
        return PLATFORM.MAC;
    }

    if (rawPlatform.includes('Win')) {
        return PLATFORM.WINDOWS;
    }

    return PLATFORM.LINUX;
}

/**
 * @private
 */
export function formatShortcutKey(key: string, platform: KeyboardPlatform): ShortcutKeyLabel {
    const normalizedKey = key.trim();
    const label = keyLabels[platform][normalizedKey.toLowerCase()];

    if (label) {
        return label;
    }

    return createKeyLabel(normalizedKey.length === 1 ? normalizedKey.toUpperCase() : normalizedKey);
}
