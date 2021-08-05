import template from './sw-my-apps-page.html.twig';
import './sw-my-apps-page.scss';

const { Component, State, Context } = Shopware;

Component.register('sw-my-apps-page', {
    template,

    inject: ['acl'],

    props: {
        appName: {
            type: String,
            required: true,
        },

        moduleName: {
            type: String,
            required: false,
            default: null,
        },
    },

    data() {
        return {
            appLoaded: false,
            timedOut: false,
            timedOutTimeout: null,
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

            if (!this.moduleName) {
                return this.appDefinition.mainModule;
            }

            return this.appDefinition.modules.find((module) => {
                return module.name === this.moduleName;
            });
        },

        suspend() {
            return !this.appDefinition || !this.moduleDefinition;
        },

        heading() {
            const appLabel = this.translate(this.appDefinition.label);
            const moduleLabel = this.translate(this.moduleDefinition.label);

            return [appLabel, moduleLabel]
                .filter((part) => !!part)
                .join(' - ');
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

        loadedMessage() {
            return 'sw-app-loaded';
        },
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
            },
        },
    },

    mounted() {
        window.addEventListener('message', this.onContentLoaded, this.$refs.innerFrame);
    },

    beforeDestroy() {
        window.removeEventListener('message', this.onContentLoaded);
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.acl.can(`app.${this.appName}`)) {
                this.$router.push({ name: 'sw.privilege.error.index' });
            }
        },

        translate(labels) {
            if (!labels) {
                return null;
            }

            return labels[this.currentLocale] || labels[this.fallbackLocale];
        },

        onContentLoaded(event) {
            if (event.origin !== this.origin) {
                return;
            }

            if (event.data === this.loadedMessage) {
                this.appLoaded = true;
            }
        },
    },
});
