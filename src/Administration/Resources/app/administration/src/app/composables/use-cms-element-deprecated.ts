/**
 * @sw-package discovery
 */
import { computed, type ComputedRef } from 'vue';
import useCmsState from './use-cms-state';
import type { CmsElementConfig, RuntimeSlot } from 'src/module/sw-cms/service/cms.service';

/**
 * The two props the mixin declared and read, handed in as getters so every read stays reactive.
 *
 * @private
 */
export interface UseCmsElementDeprecatedOptions {
    element: () => RuntimeSlot;
    defaultConfig?: () => Record<string, unknown> | null;
}

/** @private */
export type UseCmsElementDeprecatedReturn = ReturnType<typeof useCmsState> & {
    cmsElements: ComputedRef<Record<string, CmsElementConfig | undefined>>;
    initElementConfig: () => void;
    initBaseConfig: () => void;
    applyContentOverride: () => void;
    initElementData: (elementName: string) => void;
    getDemoValue: (mappingPath: string) => unknown;
};

/**
 * Composable alternative to the `cms-element` mixin, with its behaviour unchanged: every config write
 * lands on the passed `element` object itself. That object is the slot the `cmsPage` store owns, and
 * every editor component of a page edits that one reference, which is how the store stays in sync — so
 * the mutation is the contract here, not an oversight.
 *
 * The mixin composed `cms-state`, and so does this: a component that declared only `cms-element` still
 * reached the CMS editor state through it, so those members are returned as well.
 *
 * Keep this and `src/module/sw-cms/mixin/sw-cms-element.mixin.ts` in sync — change both together.
 *
 * @private
 * @deprecated tag:v6.9.0 - Use `useCmsElement` instead, which routes config writes through the
 * `cmsPage` store and leaves the element read-only.
 */
export default function useCmsElementDeprecated(options: UseCmsElementDeprecatedOptions): UseCmsElementDeprecatedReturn {
    const cmsState = useCmsState();

    // Resolved per call instead of once: the mixin injected the service, so it was never read before
    // the container had it.
    function cmsService(): ReturnType<typeof Shopware.Service<'cmsService'>> {
        return Shopware.Service('cmsService');
    }

    const cmsElements = computed(() => cmsService().getCmsElementRegistry());

    function initElementConfig(): void {
        initBaseConfig();
        applyContentOverride();
    }

    function initBaseConfig(): void {
        const { merge, get, set, has } = Shopware.Utils.object;
        const element = options.element();

        if (!element.type) {
            return;
        }

        const config = merge({}, cmsElements.value[element.type]?.defaultConfig, options.defaultConfig?.());

        if (!element.config) {
            set(element, 'config', {});
        }

        Object.entries(config).forEach(
            ([
                key,
                value,
            ]) => {
                const path = `config.${key}`;

                if (has(element, path)) {
                    return;
                }

                const newValue: unknown = get(element, `translated.${path}`, value);

                set(element, path, newValue);
            },
        );
    }

    function applyContentOverride(): void {
        const { cloneDeep, set } = Shopware.Utils.object;
        const element = options.element();

        if (!cmsState.contentEntity.value || !cmsState.inheritedSlotConfig.value || !element.id) {
            return;
        }

        const overrideConfig = cmsState.inheritedSlotConfig.value[element.id];

        if (!overrideConfig) {
            return;
        }

        Object.entries(overrideConfig).forEach(
            ([
                key,
                value,
            ]) => {
                set(element, `config.${key}`, cloneDeep(value));
            },
        );
    }

    function initElementData(elementName: string): void {
        const { cloneDeep, merge } = Shopware.Utils.object;
        const element = options.element();

        if (Shopware.Utils.types.isPlainObject(element.data) && Object.keys(element.data).length > 0) {
            return;
        }

        const elementConfig = cmsElements.value[elementName];
        const defaultData = elementConfig?.defaultData ?? {};

        element.data = merge(cloneDeep(defaultData), element.data || {});
    }

    function getDemoValue(mappingPath: string): unknown {
        return cmsService().getPropertyByMappingPath(cmsState.cmsPageState.value.currentDemoEntity, mappingPath);
    }

    return {
        ...cmsState,
        cmsElements,
        initElementConfig,
        initBaseConfig,
        applyContentOverride,
        initElementData,
        getDemoValue,
    };
}
