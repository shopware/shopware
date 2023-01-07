/**
 * @package admin
 *
 * These types of initializers are called in the end of the initialization process.
 * They depend on different initializer and can be used for setups.
 */
import initUserInformation from './user-information.init';
import initLanguage from './language.init';
import initWorker from './worker.init';
import initCookies from './cookie.init';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    cookies: initCookies,
    language: initLanguage,
    userInformation: initUserInformation,
    worker: initWorker,
};
