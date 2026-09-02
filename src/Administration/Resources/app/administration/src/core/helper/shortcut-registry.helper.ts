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
    active: () => boolean;
    /** `CTRL` or `ALT`, read from the registering component's device helper. */
    systemKey: () => string;
};

let activeShortcuts: ShortcutRegistration[] = [];
let listenerAttached = false;
let sequenceBuffer: string[] = [];
let sequenceTimeout: ReturnType<typeof setTimeout> | null = null;

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

    return isEditableDiv || /INPUT|TEXTAREA|SELECT/.test(target?.tagName ?? '');
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

function getMatchedShortcut(shortcutKey: string): ShortcutRegistration | undefined | null {
    if (isSystemShortcut(shortcutKey)) {
        resetSequenceNow();

        return findShortcut(shortcutKey);
    }

    sequenceBuffer = [
        ...sequenceBuffer,
        shortcutKey,
    ];

    const sequence = sequenceBuffer.join('');
    const matchedShortcut = findShortcut(sequence);

    scheduleSequenceReset();

    if (matchedShortcut) {
        resetSequenceNow();

        return matchedShortcut;
    }

    if (hasLongerSequenceThan(sequence)) {
        return null;
    }

    resetSequenceNow();

    return findShortcut(shortcutKey);
}

function handleKeyDown(event: KeyboardEvent): void {
    if (areShortcutsDisabled()) {
        resetSequence();

        return;
    }

    const eventTarget = event.target instanceof Element ? event.target : null;

    if (eventTarget?.closest('.sw-modal') || eventTarget?.closest('.sw-modal__dialog')) {
        resetSequence();

        return;
    }

    const systemKey = activeShortcuts[0]?.systemKey();
    const { key, altKey, ctrlKey } = event;
    const systemKeyPressed = systemKey === 'CTRL' ? ctrlKey : altKey;
    const combinedKey = (systemKeyPressed ? 'SYSTEMKEY+' : '') + key.toUpperCase();

    if (!isSystemShortcut(combinedKey) && isRestrictedSource(event)) {
        resetSequence();

        return;
    }

    const matchedShortcut = getMatchedShortcut(combinedKey);

    if (!matchedShortcut || !matchedShortcut.active()) {
        return;
    }

    // Blur editable fields, rich text and code editor inputs on save so their changes are committed
    // before the handler reads them.
    const editableTarget = eventTarget as HTMLElement | null;

    if (
        matchedShortcut.key.toUpperCase() === 'SYSTEMKEY+S' &&
        (editableTarget?.isContentEditable ||
            isRestrictedSource(event) ||
            editableTarget?.classList.contains('ace_text-input')) &&
        typeof editableTarget?.blur === 'function'
    ) {
        editableTarget.blur();
    }

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
        // eslint-disable-next-line listeners/no-inline-function-event-listener,listeners/no-missing-remove-event-listener
        document.addEventListener('keydown', (event) => handleKeyDown(event));
    }

    return () => {
        activeShortcuts = activeShortcuts.filter((shortcut) => shortcut !== registration);
    };
}
