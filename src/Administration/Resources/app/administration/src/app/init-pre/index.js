/**
 * These types of initializers are called in the beginning of the initialization process.
 * They can decorate the following initializer.
 */
import initApiServices from './api-services.init';
import initState from './state.init';

export default {
    apiServices: initApiServices,
    state: initState,
};
