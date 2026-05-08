import type { RouteLocationNamedRaw } from 'vue-router';
import type { ModuleManifest } from 'src/core/factory/module.factory';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import template from './sw-meteor-page.html.twig';
import './sw-meteor-page.scss';

type ComponentData = {
    module: ModuleManifest | null;
    parentRoute: string | null;
};

/**
 * @sw-package framework
 *
 * @private
 */
export default Shopware.Component.wrapComponentConfig({
    template,

    props: {
        fullWidth: {
            type: Boolean,
            required: false,
            default: false,
        },

        hideIcon: {
            type: Boolean,
            required: false,
            default: false,
        },

        fromLink: {
            type: Object as PropType<RouteLocationNamedRaw | null>,
            required: false,
            default: null,
        },

        pageTabs: {
            type: Array as PropType<TabItem[]>,
            required: false,
            default: () => [],
        },

        pageTabsDefaultItem: {
            type: String,
            required: false,
            default: null,
        },

        pageTabsPositionIdentifier: {
            type: String,
            required: false,
            default: 'sw-meteor-page',
        },
    },

    data(): ComponentData {
        return {
            module: null,
            parentRoute: null,
        };
    },

    computed: {
        Shopware() {
            return Shopware;
        },

        pageClasses(): object {
            return {
                'sw-meteor-page--full-width': this.fullWidth,
            };
        },

        hasIcon(): boolean {
            return typeof this.module?.icon === 'string';
        },

        hasIconOrIconSlot(): boolean {
            return this.hasIcon || typeof this.$slots['smart-bar-icon'] !== 'undefined';
        },

        hasTabs(): boolean {
            if (Shopware.Feature.isActive('V6_8_0_0')) {
                return this.pageTabs.length > 0 || typeof this.$slots['page-tabs'] !== 'undefined';
            }

            return typeof this.$slots['page-tabs'] !== 'undefined';
        },

        pageColor(): string {
            return this.module?.color ?? '#d8dde6';
        },
    },

    beforeUnmount(): void {
        void Shopware.Store.get('error').resetApiErrors();
    },

    mounted(): void {
        this.mountedComponent();
    },

    methods: {
        mountedComponent(): void {
            this.initPage();
        },

        emitNewTab(tabItem: string) {
            this.$emit('new-item-active', tabItem);
        },

        initPage(): void {
            if (typeof this.$route?.meta?.$module !== 'undefined') {
                this.module = this.$route.meta.$module as ModuleManifest | null;
            }

            if (typeof this.$route?.meta?.parentPath === 'string') {
                this.parentRoute = this.$route.meta.parentPath;
            }
        },
    },
});
