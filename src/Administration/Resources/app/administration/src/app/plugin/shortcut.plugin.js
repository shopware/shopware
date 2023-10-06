/**
 * @package admin
 */

const util = Shopware.Utils;
const { warn } = Shopware.Utils.debug;

let pluginInstalled = false;

/**
 * @deprecated tag:v6.6.0 - Will be private
 */
export default {
    install(Vue) {
        if (pluginInstalled) {
            warn('Shortcut', 'This plugin is already installed');
            return false;
        }

        let activeShortcuts = [];

        // Register component shortcuts
        Vue.mixin({
            created() {
                const shortcuts = this.$options.shortcuts;

                if (!shortcuts) {
                    return false;
                }

                // add shortcuts
                Object.entries(shortcuts).forEach(([key, value]) => {
                    if (typeof value !== 'string') {
                        const activeFunction = typeof value.active === 'boolean' ? () => value.active : value.active;

                        activeShortcuts.push({
                            key: key,
                            functionName: value.method,
                            instance: this,
                            active: activeFunction.bind(this),
                        });
                    } else {
                        activeShortcuts.push({
                            key: key,
                            functionName: value,
                            instance: this,
                            active: () => true,
                        });
                    }
                });

                // add event listener when one shortcut is registered
                if (activeShortcuts.length <= 1) {
                    document.addEventListener('keydown', this.handleKeyDownDebounce);
                }

                return true;
            },
            beforeDestroy() {
                const shortcuts = this.$options.shortcuts;

                if (!shortcuts) {
                    return false;
                }

                // remove shortcuts
                activeShortcuts = activeShortcuts.filter((activeShortcut) => {
                    return this._uid !== activeShortcut.instance._uid;
                });

                // remove event listener when no shortcuts exists
                if (activeShortcuts.length <= 0) {
                    document.removeEventListener('keydown', this.handleKeyDownDebounce);
                }

                return true;
            },
            methods: {
                handleKeyDownDebounce: util.debounce(function handleKeyDown(event) {
                    if (event.constructor !== KeyboardEvent && window.Cypress === undefined) {
                        return false;
                    }

                    const isModalShown = !!document.querySelector('.sw-modal__dialog');
                    const systemKey = this.$device.getSystemKey();
                    const { key, altKey, ctrlKey } = event;
                    const systemKeyPressed = systemKey === 'CTRL' ? ctrlKey : altKey;

                    if (isModalShown) {
                        return false;
                    }

                    // create combined key name and look for matching shortcut
                    const combinedKey = `${systemKeyPressed ? 'SYSTEMKEY+' : ''}${key.toUpperCase()}`;
                    const matchedShortcut = activeShortcuts.find((shortcut) => shortcut.key.toUpperCase() === combinedKey);

                    let shouldNotTrigger = false;

                    if (matchedShortcut && !matchedShortcut.active()) {
                        return false;
                    }

                    // check for editable elements
                    const isEditableDiv = event.target.tagName === 'DIV' && event.target.isContentEditable;

                    // SYSTEMKEY shortcuts combinations should always trigger
                    if (matchedShortcut && /SYSTEMKEY/.test(matchedShortcut.key) === false) {
                        // check for restricted elements
                        const restrictedTags = /INPUT|TEXTAREA|SELECT/;
                        const isRestrictedTag = restrictedTags.test(event.target.tagName);

                        shouldNotTrigger = isEditableDiv || isRestrictedTag;
                    }

                    // check for situations where the shortcut should not trigger
                    if (shouldNotTrigger ||
                        !matchedShortcut ||
                        !matchedShortcut.instance ||
                        !matchedShortcut.functionName) {
                        return false;
                    }

                    // blur rich text and code editor inputs on save shortcut to react on changes before saving
                    if (matchedShortcut.key === 'SYSTEMKEY+S' &&
                        (isEditableDiv || event.target.classList.contains('ace_text-input'))) {
                        event.target.blur();
                    }

                    // check if function exists
                    if (matchedShortcut.instance[matchedShortcut.functionName]) {
                        // trigger function
                        matchedShortcut.instance[matchedShortcut.functionName].call(matchedShortcut.instance);
                    }

                    return true;
                }, 200),
            },
        });

        pluginInstalled = true;

        return true;
    },
};
