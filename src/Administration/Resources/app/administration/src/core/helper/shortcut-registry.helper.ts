/**
 * @sw-package framework
 */

/**
 * The one registry every keyboard shortcut goes through, so the Options API `shortcuts` option and
 * `useShortcut()` share a single match order and a single keydown listener. Registration is by
 * callback and identity: an entry is removed through the function that registered it, never by
 * looking it up again by key.
 *
 * @private
 */

const COMPONENT_SHORTCUT_KEYSTROKE_DELAY = 1000;

/** @private */
export type ShortcutRegistration = {
    key: string;
    handler: () => void;
    /** Whether the shortcut may fire right now — evaluated on every keystroke, never cached. */
    active: (event: KeyboardEvent) => boolean;
};

type DeviceHelper = { getSystemKey: () => string };

/** Shape of the `shortcut` factory slot, which the DI container types as `$TSFixMe`. */
type ShortcutFactory = { getShortcutRegistry?: () => Map<string, string> };

let activeShortcuts: ShortcutRegistration[] = [];
let listenerAttached = false;
let sequenceBuffer: string[] = [];
let sequenceTimeout: ReturnType<typeof setTimeout> | null = null;
let deviceHelper: DeviceHelper | null = null;

/**
 * `CTRL` on macOS, `ALT` elsewhere. The system key is a platform property, the same for every
 * shortcut, so the registry resolves it once through its own device helper rather than letting each
 * registration carry one. `$device` is an Options API global the registry cannot reach anyway.
 */
function systemKey(): string {
    if (!deviceHelper) {
        const helper: object = new Shopware.Helper.DeviceHelper();

        if (!isDeviceHelper(helper)) {
            throw new Error('Shopware.Helper.DeviceHelper does not provide getSystemKey()');
        }

        deviceHelper = helper;
    }

    return deviceHelper.getSystemKey();
}

/** `DeviceHelper` is a JS constructor function, so TypeScript cannot see the methods on its prototype. */
function isDeviceHelper(helper: object): helper is DeviceHelper {
    return 'getSystemKey' in helper && typeof helper.getSystemKey === 'function';
}

function areShortcutsDisabled(): boolean {
    const shortcutService = Shopware.Service('shortcutService') as { isShortcutsDisabled?: () => boolean } | undefined;

    return shortcutService?.isShortcutsDisabled?.() === true;
}

function resetSequence(): void {
    sequenceBuffer = [];
}

/** A multi-key sequence is only a sequence while the next keystroke follows quickly enough. */
function scheduleSequenceReset(): void {
    if (sequenceTimeout !== null) {
        clearTimeout(sequenceTimeout);
    }

    sequenceTimeout = setTimeout(resetSequence, COMPONENT_SHORTCUT_KEYSTROKE_DELAY);
}

function resetSequenceNow(): void {
    if (sequenceTimeout !== null) {
        clearTimeout(sequenceTimeout);
        sequenceTimeout = null;
    }

    resetSequence();
}

function isSystemShortcut(shortcutKey: string): boolean {
    return /SYSTEMKEY/.test(shortcutKey);
}

function isRestrictedSource(event: KeyboardEvent): boolean {
    const target = event.target as HTMLElement | null;
    const isEditableDiv = target?.tagName === 'DIV' && target.isContentEditable;
    // A focused sw-select or mt-select is a form field like a native SELECT, even while it is closed
    const isSelect = target?.matches?.('.sw-select__selection, .mt-select__selection') ?? false;

    return isEditableDiv || /INPUT|TEXTAREA|SELECT/.test(target?.tagName ?? '') || isSelect;
}

function findShortcut(key: string): ShortcutRegistration | undefined {
    return activeShortcuts.find((shortcut) => shortcut.key.toUpperCase() === key);
}

function hasLongerSequenceThan(sequence: string): boolean {
    return activeShortcuts.some((shortcut) => {
        const registeredKey = shortcut.key.toUpperCase();

        return !isSystemShortcut(registeredKey) && registeredKey.startsWith(sequence) && registeredKey !== sequence;
    });
}

/**
 * Global navigation shortcuts live in their own registry, outside this one. Their prefixes still have
 * to hold the buffer open, or a sequence like `MP` would fire `M`'s single-key match on the first key.
 */
function isNavigationSequence(sequence: string): boolean {
    const factoryContainer = Shopware.Application.getContainer('factory') as { shortcut?: ShortcutFactory } | undefined;
    const shortcutFactory = factoryContainer?.shortcut;
    const registry = shortcutFactory?.getShortcutRegistry?.();

    if (!registry) {
        return false;
    }

    return [...registry.keys()].some((combination) => combination.toUpperCase().startsWith(sequence));
}

function getMatchedShortcut(shortcutKey: string, allowSequence: boolean): ShortcutRegistration | undefined {
    if (isSystemShortcut(shortcutKey) || !allowSequence) {
        resetSequenceNow();

        return findShortcut(shortcutKey);
    }

    sequenceBuffer = [...sequenceBuffer, shortcutKey];

    const sequence = sequenceBuffer.join('');
    const matchedShortcut = findShortcut(sequence);

    scheduleSequenceReset();

    if (matchedShortcut) {
        resetSequenceNow();

        return matchedShortcut;
    }

    // Prefix of a longer sequence: keep the buffer and wait for the next key instead of falling
    // through to a single-key match.
    if (hasLongerSequenceThan(sequence) || isNavigationSequence(sequence)) {
        return undefined;
    }

    resetSequenceNow();

    return findShortcut(shortcutKey);
}

function isSystemKeyPressed(event: KeyboardEvent): boolean {
    return systemKey() === 'CTRL' ? event.ctrlKey : event.altKey;
}

function combinedKeyOf(event: KeyboardEvent): string {
    return (isSystemKeyPressed(event) ? 'SYSTEMKEY+' : '') + event.key.toUpperCase();
}

/** Keystrokes that may never fire a shortcut. They also drop a half-typed sequence. */
function isBlockedKeystroke(event: KeyboardEvent): boolean {
    if (areShortcutsDisabled()) {
        return true;
    }

    if (event.target instanceof Element && event.target.closest('.sw-modal, .sw-modal__dialog, .mt-modal')) {
        return true;
    }

    return !isSystemKeyPressed(event) && isRestrictedSource(event);
}

/** Blur editable fields, rich text and code editor inputs on save so the handler reads their committed value. */
function blurEditableTargetOnSave(event: KeyboardEvent, shortcut: ShortcutRegistration): void {
    const target = event.target instanceof HTMLElement ? event.target : null;

    if (!target || shortcut.key.toUpperCase() !== 'SYSTEMKEY+S') {
        return;
    }

    if (target.isContentEditable || isRestrictedSource(event) || target.classList.contains('ace_text-input')) {
        target.blur();
    }
}

function handleKeyDown(event: KeyboardEvent): void {
    if (event.repeat) {
        return;
    }

    if (isBlockedKeystroke(event)) {
        resetSequence();

        return;
    }

    // Browser shortcuts like Ctrl+C keep matching single keys, but never start or continue a key sequence
    const isModifiedKey = event.altKey || event.ctrlKey || event.metaKey;
    const matchedShortcut = getMatchedShortcut(combinedKeyOf(event), !isModifiedKey);

    if (!matchedShortcut?.active(event)) {
        return;
    }

    blurEditableTargetOnSave(event, matchedShortcut);
    matchedShortcut.handler();
}

/**
 * Adds one shortcut and returns the function that removes it again. The keydown listener is attached
 * with the first registration and intentionally never removed: it stays for the lifetime of the
 * application so global shortcuts keep working.
 *
 * @private
 */
export function registerShortcut(registration: ShortcutRegistration): () => void {
    activeShortcuts.push(registration);

    if (!listenerAttached) {
        listenerAttached = true;
        // eslint-disable-next-line listeners/no-missing-remove-event-listener -- the listener lives as long as the app
        document.addEventListener('keydown', handleKeyDown);
    }

    return () => {
        activeShortcuts = activeShortcuts.filter((shortcut) => shortcut !== registration);
    };
}
