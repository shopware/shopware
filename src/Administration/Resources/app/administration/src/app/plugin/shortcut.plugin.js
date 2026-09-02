/**
 * @sw-package framework
 */

const componentShortcutKeystrokeDelay = 1000;
/**
 * @private
 */
export default {
    install(Vue) {
        let activeShortcuts = [];
        // Component shortcuts trigger instance methods, so they keep their own keydown sequence state.
        let componentShortcutState = {
            buffer: [],
        };

        function areShortcutsDisabled() {
            return Shopware.Service('shortcutService')?.isShortcutsDisabled?.() === true;
        }

        function resetComponentShortcutState() {
            componentShortcutState = {
                buffer: [],
            };
        }

        const resetComponentShortcutStateDebounced = Shopware.Utils.debounce(
            resetComponentShortcutState,
            componentShortcutKeystrokeDelay,
        );

        function resetSequenceNow() {
            resetComponentShortcutStateDebounced.cancel?.();
            resetComponentShortcutState();
        }

        function isSystemShortcut(shortcutKey) {
            return /SYSTEMKEY/.test(shortcutKey);
        }

        function isRestrictedSource(event) {
            const isEditableDiv = event.target.tagName === 'DIV' && event.target.isContentEditable;
            const restrictedTags = /INPUT|TEXTAREA|SELECT/;
            const isRestrictedTag = restrictedTags.test(event.target.tagName);

            return isEditableDiv || isRestrictedTag;
        }

        function findShortcut(key) {
            return activeShortcuts.find((shortcut) => shortcut.key.toUpperCase() === key);
        }

        function hasLongerSequenceThan(sequence) {
            return activeShortcuts.some((shortcut) => {
                const registeredKey = shortcut.key.toUpperCase();

                return !isSystemShortcut(registeredKey) && registeredKey.startsWith(sequence) && registeredKey !== sequence;
            });
        }

        function getMatchedShortcut(shortcutKey) {
            if (isSystemShortcut(shortcutKey)) {
                resetSequenceNow();

                return findShortcut(shortcutKey);
            }

            const buffer = [
                ...componentShortcutState.buffer,
                shortcutKey,
            ];
            const sequence = buffer.join('');
            const matchedShortcut = findShortcut(sequence);

            componentShortcutState = {
                buffer,
            };
            resetComponentShortcutStateDebounced();

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

        function handleKeyDown(event) {
            if (areShortcutsDisabled()) {
                resetComponentShortcutState();

                return;
            }

            // Check if event originates from within a modal
            const eventTarget = event.target instanceof Element ? event.target : null;
            const isFromModal = eventTarget?.closest('.sw-modal') || eventTarget?.closest('.sw-modal__dialog');

            if (isFromModal) {
                resetComponentShortcutState();

                return;
            }

            // The 'this' context is the component instance, bound via .call()
            const systemKey = this.$device.getSystemKey();
            const { key, altKey, ctrlKey } = event;
            const systemKeyPressed = systemKey === 'CTRL' ? ctrlKey : altKey;

            // create combined key name and look for matching shortcut
            const combinedKey = (systemKeyPressed ? 'SYSTEMKEY+' : '') + key.toUpperCase();

            if (!isSystemShortcut(combinedKey) && isRestrictedSource(event)) {
                resetComponentShortcutState();

                return;
            }

            const matchedShortcut = getMatchedShortcut(combinedKey);

            if (!matchedShortcut) {
                return;
            }

            if (!matchedShortcut.active()) {
                return;
            }

            // check for situations where the shortcut should not trigger
            if (!matchedShortcut.instance || !matchedShortcut.functionName) {
                return;
            }

            // blur editable fields, rich text and code editor inputs on save shortcut to react on changes before saving
            if (
                matchedShortcut.key === 'SYSTEMKEY+S' &&
                (eventTarget?.isContentEditable ||
                    isRestrictedSource(event) ||
                    eventTarget?.classList.contains('ace_text-input')) &&
                typeof eventTarget?.blur === 'function'
            ) {
                eventTarget.blur();
            }

            // check if function exists
            if (typeof matchedShortcut.instance[matchedShortcut.functionName] === 'function') {
                // trigger function
                matchedShortcut.instance[matchedShortcut.functionName].call(matchedShortcut.instance);
            }
        }

        // Register component shortcuts
        Vue.mixin({
            created() {
                const shortcuts = this.$options.shortcuts;

                if (!shortcuts) {
                    return;
                }

                const initialLength = activeShortcuts.length;

                // add shortcuts
                Object.entries(shortcuts).forEach(
                    ([
                        key,
                        value,
                    ]) => {
                        const shortcut = {
                            key: key,
                            instance: this,
                        };

                        if (typeof value !== 'string') {
                            shortcut.functionName = value.method;
                            shortcut.active = (typeof value.active === 'boolean' ? () => value.active : value.active).bind(
                                this,
                            );
                        } else {
                            shortcut.functionName = value;
                            shortcut.active = () => true;
                        }

                        activeShortcuts.push(shortcut);
                    },
                );

                // add event listener only for the first component with shortcuts
                if (initialLength === 0 && activeShortcuts.length > 0) {
                    // The event listener is intentionally not removed to keep global shortcuts working.
                    // It will be active for the lifetime of the application, which is acceptable.
                    // eslint-disable-next-line listeners/no-inline-function-event-listener,listeners/no-missing-remove-event-listener
                    document.addEventListener('keydown', (event) => {
                        // Find any active component instance to get the context for $device
                        const anyInstance = activeShortcuts[0]?.instance;
                        if (anyInstance) {
                            handleKeyDown.call(anyInstance, event);
                        }
                    });
                }
            },
            beforeUnmount() {
                const shortcuts = this.$options.shortcuts;

                if (!shortcuts) {
                    return;
                }

                // remove shortcuts of this component instance
                const shortcutKeys = Object.keys(shortcuts);
                activeShortcuts = activeShortcuts.filter((activeShortcut) => {
                    return !(activeShortcut.instance.$.uid === this.$.uid && shortcutKeys.includes(activeShortcut.key));
                });

                // The event listener is intentionally not removed to keep global shortcuts working.
                // It will be active for the lifetime of the application, which is acceptable.
            },
        });
    },
};
