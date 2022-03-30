/**
 * These types of initializers are called in the end of the initialization process.
 * They depend on different initializer and can be used for setups.
 */
import initUserInformation from './user-information.init';
import initLanguage from './language.init';
import initWorker from './worker.init';
import initMarketing from './marketing.init';
import initCookies from './cookie.init';

export default {
    cookies: initCookies,
    language: initLanguage,
    userInformation: initUserInformation,
    worker: initWorker,
    marketing: initMarketing,
};
