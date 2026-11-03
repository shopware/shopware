/**
 * @sw-package discovery
 *
 * Everything sw-media-compact-upload-v2 needs from outside its mini-module.
 */
import { mediaNameFilterName } from 'src/app/filter/media-name.filter';

/** @private */
export const getMediaNameFilter = () => Shopware.Filter.getByName(mediaNameFilterName);
