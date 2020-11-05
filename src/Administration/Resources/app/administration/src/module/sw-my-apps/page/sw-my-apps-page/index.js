import template from './sw-my-apps-page.html.twig';
import './sw-my-apps-page.scss';

const { Component, State, Context } = Shopware;

Component.register('sw-my-apps-page', {
    template,

    props: {
        appName: {
            type: String,
            required: true
        },

        moduleName: {
            type: String,
            required: true
        }
    },

    data() {
        return {
            appLoaded: false,
            timedOut: false,
            timedOutTimeout: null
        };
    },

    computed: {
        currentLocale() {
            return State.get('session').currentLocale;
        },

        fallbackLocale() {
            return Context.app.fallbackLocale;
        },

        appDefinition() {
            return State.get('shopwareApps').apps.find((app) => {
                return app.name === this.appName;
            });
        },

        moduleDefinition() {
            if (!this.appDefinition) {
                return null;
            }

            return this.appDefinition.modules.find((module) => {
                return module.name === this.moduleName;
            });
        },

        suspend() {
            return !this.appDefinition || !this.moduleDefinition;
        },

        heading() {
            if (this.suspend) {
                return this.$tc('sw-my-apps.general.mainMenuItemGeneral');
            }

            const appLabel = this.translate(this.appDefinition.label);
            const moduleLabel = this.translate(this.moduleDefinition.label);

            const spacer = !appLabel || !moduleLabel ? '' : ' - ';

            return `${appLabel}${spacer}${moduleLabel}`;
        },

        entryPoint() {
            if (this.suspend) {
                return null;
            }

            return this.moduleDefinition.source;
        },

        origin() {
            if (!this.entryPoint) {
                return null;
            }

            try {
                const url = new URL(this.entryPoint);
                return url.origin;
            } catch (e) {
                return null;
            }
        },

        innerFrame() {
            return this.$refs.innerFrame;
        },

        /* @deprecated tag:v6.4.0.0 */
        legacyLoadedMessage() {
            return 'connect-app-loaded';
        },

        loadedMessage() {
            return 'sw-app-loaded';
        }
    },

    watch: {
        entryPoint() {
            this.appLoaded = false;
            this.timedOut = false;
        },

        appLoaded: {
            immediate: true,
            handler(loaded) {
                clearTimeout(this.timedOutTimeout);
                this.timedOutTimeout = null;

                if (!loaded) {
                    this.timedOutTimeout = setTimeout(() => {
                        if (!this.appLoaded) {
                            this.timedOut = true;
                        }
                    }, 5000);
                }
            }
        }
    },

    mounted() {
        window.addEventListener('message', this.onContentLoaded, this.$refs.innerFrame);
    },

    beforeDestroy() {
        window.removeEventListener('message', this.onContentLoaded);
    },

    methods: {
        translate(labels) {
            return labels[this.currentLocale] || labels[this.fallbackLocale];
        },

        onContentLoaded(event) {
            if (event.origin !== this.origin) {
                return;
            }

            if (event.data === this.legacyLoadedMessage ||
                event.data === this.loadedMessage) {
                this.appLoaded = true;
            }
        }
    }
});
