/**
 * These types of initializers are called in the end of the initialization process.
 * They depend on different initializer and can be used for setups.
 */
import initUserInformation from './user-information.init';
import initWorker from './worker.init';

export default {
    userInformation: initUserInformation,
    worker: initWorker
};
