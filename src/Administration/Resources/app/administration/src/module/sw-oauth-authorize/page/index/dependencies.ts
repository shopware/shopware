/**
 * @sw-package framework
 *
 * Everything sw-oauth-authorize-index needs from outside its mini-module.
 */
import { assetFilterName } from 'src/app/filter/asset.filter';
import { sessionStoreId } from 'src/app/store/session';

/** @private */
export { defineComponent as wrapComponentConfig } from 'vue';
/** @private */
export type { OAuthAuthorizationInfo, OAuthAuthorizationParams } from 'src/core/service/api/oauth-authorize.api.service';
/** @private */
export { oauthAuthorizeApiService, systemConfigApiService } from 'src/app/service-names';

/** @private */
export const useSessionStore = () => Shopware.Store.get(sessionStoreId);

/** @private */
export const getAssetFilter = () => Shopware.Filter.getByName(assetFilterName);
