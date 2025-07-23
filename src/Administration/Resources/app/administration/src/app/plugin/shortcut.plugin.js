/**
 * @sw-package framework
 */

const util = Shopware.Utils;

/**
 * @private
 */
export default {
    install(Vue) {
        let activeShortcuts = [];

        const handleKeyDownDebounce = util.debounce(function handleKeyDown(event) {
            if (event.constructor !== KeyboardEvent && window.Cypress === undefined) {
                return;
            }

            // Check if event originates from within a modal
            const eventTarget = event.target instanceof Element ? event.target : null;
            const isFromModal = eventTarget?.closest('.sw-modal') || eventTarget?.closest('.sw-modal__dialog');

            if (isFromModal) {
                return;
            }

            // The 'this' context is the component instance, bound via .call()
            const systemKey = this.$device.getSystemKey();
            const { key, altKey, ctrlKey } = event;
            const systemKeyPressed = systemKey === 'CTRL' ? ctrlKey : altKey;

            // create combined key name and look for matching shortcut
            const combinedKey = `${systemKeyPressed ? 'SYSTEMKEY+' : ''}${key.toUpperCase()}`;
            const matchedShortcut = activeShortcuts.find((shortcut) => shortcut.key.toUpperCase() === combinedKey);

            if (!matchedShortcut) {
                return;
            }

            if (!matchedShortcut.active()) {
                return;
            }

            // check for editable elements
            const isEditableDiv = event.target.tagName === 'DIV' && event.target.isContentEditable;
            let shouldNotTrigger = false;

            // SYSTEMKEY shortcuts combinations should always trigger
            if (/SYSTEMKEY/.test(matchedShortcut.key) === false) {
                // check for restricted elements
                const restrictedTags = /INPUT|TEXTAREA|SELECT/;
                const isRestrictedTag = restrictedTags.test(event.target.tagName);

                shouldNotTrigger = isEditableDiv || isRestrictedTag;
            }

            // check for situations where the shortcut should not trigger
            if (shouldNotTrigger || !matchedShortcut.instance || !matchedShortcut.functionName) {
                return;
            }

            // blur rich text and code editor inputs on save shortcut to react on changes before saving
            if (
                matchedShortcut.key === 'SYSTEMKEY+S' &&
                (isEditableDiv || event.target.classList.contains('ace_text-input'))
            ) {
                event.target.blur();
            }

            // check if function exists
            if (typeof matchedShortcut.instance[matchedShortcut.functionName] === 'function') {
                // trigger function
                matchedShortcut.instance[matchedShortcut.functionName].call(matchedShortcut.instance);
            }
        }, 200);

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
                    document.addEventListener('keydown', (event) => {
                        // Find any active component instance to get the context for $device
                        const anyInstance = activeShortcuts[0]?.instance;
                        if (anyInstance) {
                            handleKeyDownDebounce.call(anyInstance, event);
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
                    return !(activeShortcut.instance._uid === this._uid && shortcutKeys.includes(activeShortcut.key));
                });

                // The event listener is intentionally not removed to keep global shortcuts working.
                // It will be active for the lifetime of the application, which is acceptable.
            },
        });
    },
};
