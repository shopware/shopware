import { warn } from 'src/core/service/utils/debug.utils';

let pluginInstalled = false;

export default {
    install(Vue) {
        if (pluginInstalled) {
            warn('Shortcut', 'This plugin is already installed');
            return false;
        }

        // Register component shortcuts
        Vue.mixin({
            created() {
                const shortcuts = this.$options.shortcuts;

                if (!shortcuts) {
                    return false;
                }

                document.addEventListener('keyup', this.debounce(this.handleKeyUp));

                return true;
            },
            beforeDestroy() {
                const shortcuts = this.$options.shortcuts;

                if (!shortcuts) {
                    return false;
                }

                document.removeEventListener('keyup', this.debounce(this.handleKeyUp));

                return true;
            },
            methods: {
                debounce(fn, time = 100) {
                    let timeout;

                    return (...args) => {
                        const functionCall = () => fn.apply(this, args);

                        clearTimeout(timeout);
                        timeout = setTimeout(functionCall, time);
                    };
                },

                handleKeyUp(event) {
                    const isModalShown = !!document.querySelector('.sw-modal__dialog');
                    const shortcuts = this.$options.shortcuts;
                    const systemKey = this.$device.getSystemKey();
                    const { key, altKey, ctrlKey } = event;

                    if (isModalShown || typeof shortcuts !== 'object') {
                        return false;
                    }

                    Object.keys(shortcuts).forEach((combination) => {
                        const method = shortcuts[combination];
                        let executeable = false;

                        // combination with modifier?
                        if (combination.indexOf('+') > 1) {
                            const [modifier, shortcut] = combination.split('+');
                            const translatedModifier = modifier === 'SYSTEMKEY'
                                ? systemKey
                                : modifier.toUpperCase();

                            executeable = (
                                (translatedModifier === 'ALT' && altKey)
                                || (translatedModifier === 'CTRL' && ctrlKey)
                            ) && key.toUpperCase() === shortcut.toUpperCase();
                        } else {
                            const shortcut = combination.toUpperCase();
                            const source = event.srcElement;
                            const tagName = source.tagName;
                            const isEditableDiv = tagName === 'DIV' && source.isContentEditable;
                            const restrictedTags = /INPUT|TEXTAREA|SELECT/;
                            const isRestrictedTag = restrictedTags.test(tagName);

                            executeable = !(isEditableDiv || isRestrictedTag)
                                && !(altKey || ctrlKey)
                                && key.toUpperCase() === shortcut;
                        }

                        if (executeable) {
                            this[method].call(this);
                        }

                        return true;
                    });

                    return true;
                }
            }
        });

        pluginInstalled = true;

        return true;
    }
};
