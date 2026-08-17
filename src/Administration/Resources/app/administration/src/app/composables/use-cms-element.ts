/**
 * @sw-package discovery
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_MIXIN_COMPOSABLES
 */
import { computed, type ComputedRef } from 'vue';
import useCmsState from './use-cms-state';
import 'src/module/sw-cms/store/cms-page.store';
import type { CmsElementConfig, CmsSlotConfig, RuntimeSlot } from 'src/module/sw-cms/service/cms.service';

/** @private */
export interface UseCmsElementOptions {
    element: () => RuntimeSlot;
    defaultConfig?: () => Record<string, unknown> | null;
}

/** @private */
export type UseCmsElementReturn = ReturnType<typeof useCmsState> & {
    cmsElements: ComputedRef<Record<string, CmsElementConfig | undefined>>;
    config: ComputedRef<CmsSlotConfig>;
    getConfigValue: (path: string) => unknown;
    setConfigValue: (path: string, value: unknown) => void;
    getDemoValue: (mappingPath: string) => unknown;
};

/**
 * The CMS element API for editor components: the config of the element they render, resolved against
 * the element type's defaults and the inherited slot config, plus the writes that change it.
 *
 * An element belongs to the page the `cmsPage` store owns, so a component reads its element and asks
 * the store to change it — `config` resolves the defaults on read instead of writing them into the
 * element, and `setConfigValue` goes through the store action. Both take a path relative to the
 * element's `config`, e.g. `'media.value'`.
 *
 * @private
 */
export default function useCmsElement(options: UseCmsElementOptions): UseCmsElementReturn {
    const cmsState = useCmsState();

    function cmsService(): ReturnType<typeof Shopware.Service<'cmsService'>> {
        return Shopware.Service('cmsService');
    }

    const cmsElements = computed(() => cmsService().getCmsElementRegistry());

    const config = computed<CmsSlotConfig>(() => {
        const { merge, get, set, cloneDeep } = Shopware.Utils.object;
        const element = options.element();
        const resolved = cloneDeep(element.config ?? {});
        const defaults = merge({}, cmsElements.value[element.type ?? '']?.defaultConfig, options.defaultConfig?.());

        Object.entries(defaults).forEach(
            ([
                key,
                value,
            ]) => {
                if (key in resolved) {
                    return;
                }

                set(resolved, key, get(element, `translated.config.${key}`, value));
            },
        );

        const override = element.id ? cmsState.inheritedSlotConfig.value?.[element.id] : null;

        Object.entries(override ?? {}).forEach(
            ([
                key,
                value,
            ]) => {
                set(resolved, key, cloneDeep(value));
            },
        );

        return resolved;
    });

    function getConfigValue(path: string): unknown {
        return Shopware.Utils.object.get(config.value, path);
    }

    function setConfigValue(path: string, value: unknown): void {
        Shopware.Store.get('cmsPage').updateElementConfig(options.element().id, path, value);
    }

    function getDemoValue(mappingPath: string): unknown {
        return cmsService().getPropertyByMappingPath(cmsState.cmsPageState.value.currentDemoEntity, mappingPath);
    }

    return {
        ...cmsState,
        cmsElements,
        config,
        getConfigValue,
        setConfigValue,
        getDemoValue,
    };
}
