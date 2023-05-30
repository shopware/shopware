/**
 * @package admin
 *
 * These types of initializers are called in the beginning of the initialization process.
 * They can decorate the following initializer.
 */
import initApiServices from './api-services.init';
import initState from './state.init';

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default {
    apiServices: initApiServices,
    state: initState,
};
