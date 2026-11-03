/**
 * @sw-package discovery
 *
 * Everything sw-media-preview-v2 needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { mediaNameFilterName } from 'src/app/filter/media-name.filter';

/** @private */
export { default as EventBus } from 'src/core/service/utils/eventBus.utils';
/** @private */
export { feature as featureService, repositoryFactory as repositoryFactoryService } from 'src/app/service-names';
/** @private */
export { isPlayableMediaFormat, shouldShowUnsupportedFormatWarning } from 'src/app/service/media-format.service';

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);

/** @private */
export const getMediaNameFilter = () => Shopware.Filter.getByName(mediaNameFilterName);

/** @private */
export const getApiContext = () => Shopware.Context.api;

/** @private */
export const readAsDataURL = ((...args: unknown[]) =>
    (Shopware.Utils.fileReader.readAsDataURL as (...args: unknown[]) => unknown)(
        ...args,
    )) as typeof Shopware.Utils.fileReader.readAsDataURL;
