/**
 * @sw-package framework
 *
 * Everything sw-user-sso-invitation-modal needs from outside its mini-module.
 */
import { repositoryFactory, ssoInvitationService } from 'src/app/service-names';

/** @private */
export { default as ShopwareError } from 'src/core/data/ShopwareError';

/** @private */
export const getRepositoryFactory = () => Shopware.Service(repositoryFactory);

/** @private */
export const getSsoInvitationService = () => Shopware.Service(ssoInvitationService);

/** @private */
export const getApiContext = () => Shopware.Context.api;
