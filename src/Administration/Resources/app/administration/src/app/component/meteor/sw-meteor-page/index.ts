import type { RouteLocationNamedRaw } from 'vue-router';
import type { TabItem } from '@shopware-ag/meteor-component-library/dist/esm/MtTabs';
import type { ModuleManifest } from 'src/core/factory/module.factory';
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

        tabItems: {
            type: Array as PropType<TabItem[]>,
            required: false,
            default: () => [],
        },
    },

    data(): ComponentData {
        return {
            module: null,
            parentRoute: null,
        };
    },

    computed: {
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
            return this.useMeteorTabs || typeof this.$slots['page-tabs'] !== 'undefined';
        },

        useMeteorTabs(): boolean {
            return Shopware.Feature.isActive('V6_8_0_0') && this.tabItems.length > 0;
        },

        activeTab(): string {
            const itemNames = this.tabItems.map((item) => item.name);

            if (itemNames.includes(String(this.$route.name))) {
                return String(this.$route.name);
            }

            return itemNames[0] ?? '';
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
