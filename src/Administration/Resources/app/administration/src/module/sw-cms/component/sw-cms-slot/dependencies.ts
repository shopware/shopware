/**
 * @sw-package discovery
 *
 * Everything sw-cms-slot needs from outside its mini-module.
 */
import { cmsPageStoreId } from '../../store/cms-page.store';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export { cmsElementFavorites as cmsElementFavoritesService, cmsService } from 'src/app/service-names';
/** @private */
export type { CmsElementConfig } from '../../service/cms.service';

/** @private */
export const useCmsPageStore = () => Shopware.Store.get(cmsPageStoreId);

/** @private */
export const deepCopyObject = ((...args: unknown[]) =>
    (Shopware.Utils.object.deepCopyObject as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.object.deepCopyObject;
