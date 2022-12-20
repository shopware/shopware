import type { AppModuleDefinition } from 'src/core/service/api/app-modules.service';
import template from './sw-extension-app-module-page.html.twig';
import './sw-extension-app-module-page.scss';

const { State, Context } = Shopware;

/**
 * @package merchant-services
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
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

    data(): { appLoaded: boolean, timedOut: boolean, timedOutTimeout: null|number } {
        return {
            appLoaded: false,
            timedOut: false,
            timedOutTimeout: null,
        };
    },

    computed: {
        currentLocale(): string|null {
            return State.get('session').currentLocale;
        },

        fallbackLocale(): string|null {
            return Context.app.fallbackLocale;
        },

        appDefinition(): AppModuleDefinition|null {
            return State.get('shopwareApps').apps.find((app) => {
                return app.name === this.appName;
            }) ?? null;
        },

        moduleDefinition(): Partial<{ source: string, label: {[key: string]: string} }>|null {
            if (!this.appDefinition) {
                return null;
            }

            if (!this.moduleName) {
                return this.appDefinition.mainModule ?? null;
            }

            return this.appDefinition.modules.find((module) => {
                return module.name === this.moduleName;
            }) ?? null;
        },

        suspend(): boolean {
            return !this.appDefinition || !this.moduleDefinition;
        },

        heading(): string|null {
            if (!this.appDefinition) {
                return null;
            }

            const appLabel = this.translate(this.appDefinition.label);

            if (!this.moduleDefinition || !this.moduleDefinition.label) {
                return appLabel;
            }

            const moduleLabel = this.translate(this.moduleDefinition.label);

            return [appLabel, moduleLabel]
                .filter((part) => !!part)
                .join(' - ');
        },

        entryPoint(): string|null {
            if (this.suspend) {
                return null;
            }

            return this.moduleDefinition?.source ?? null;
        },

        origin(): string|null {
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

        loadedMessage(): 'sw-app-loaded' {
            return 'sw-app-loaded';
        },
    },

    watch: {
        entryPoint(): void {
            this.appLoaded = false;
            this.timedOut = false;
        },

        appLoaded: {
            immediate: true,
            handler(loaded) {
                if (this.timedOutTimeout !== null) {
                    clearTimeout(this.timedOutTimeout);
                }

                this.timedOutTimeout = null;

                if (!loaded) {
                    this.timedOutTimeout = window.setTimeout(() => {
                        if (!this.appLoaded) {
                            this.timedOut = true;
                        }
                    }, 5000);
                }
            },
        },
    },

    mounted() {
        // eslint-disable-next-line @typescript-eslint/unbound-method
        window.addEventListener('message', this.onContentLoaded);
    },

    beforeDestroy() {
        // eslint-disable-next-line @typescript-eslint/unbound-method
        window.removeEventListener('message', this.onContentLoaded);
    },

    created() {
        this.createdComponent();
    },

    methods: {
        createdComponent() {
            if (!this.acl.can(`app.${this.appName}`)) {
                // ignore promise from push because this page should be destroyed after the url change
                void this.$router.push({ name: 'sw.privilege.error.index' });
            }
        },

        translate(labels : {[key:string]: string}): string|null {
            if (this.currentLocale && labels[this.currentLocale]) {
                return labels[this.currentLocale];
            }

            if (this.fallbackLocale && labels[this.fallbackLocale]) {
                return labels[this.fallbackLocale];
            }

            return null;
        },

        onContentLoaded(event: MessageEvent): void {
            if (event.origin !== this.origin) {
                return;
            }

            if (event.data === this.loadedMessage) {
                this.appLoaded = true;
            }
        },
    },
});
