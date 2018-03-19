import { State } from 'src/core/shopware';
import utils from 'src/core/service/util.service';

State.register('error', {
    namespaced: true,
    strict: true,

    state() {
        return {
            system: []
        };
    },

    mutations: {
        addError(state, payload) {
            if (!payload.error) {
                return false;
            }

            const error = payload.error;
            const module = payload.module || 'system';

            error.id = utils.createId();
            error.module = module;

            if (!state[module]) {
                state[module] = {};
            }

            if (module !== 'system' && error.source && error.source.pointer) {
                error.propertyDepth = error.source.pointer.split('/');
                error.propertyPath = `${module}${error.propertyDepth.join('.')}`;

                error.propertyDepth.reduce((obj, key, i) => {
                    if (!key.length || key.length <= 0) {
                        return obj;
                    }

                    obj[key] = (i === error.propertyDepth.length - 1) ? error : {};

                    return obj[key];
                }, state[module]);
            } else {
                state.system.push(error);

                this.dispatch('notification/createNotification', {
                    variant: 'error',
                    title: error.title,
                    message: error.detail
                });
            }

            return error;
        },

        deleteError(state, error) {
            if (!error || !error.module) {
                return false;
            }

            if (error.module === 'system') {
                state.system = state.system.filter((item) => {
                    return item.id !== error.id;
                });
            } else {
                error.propertyDepth.reduce((obj, key, index) => {
                    if (!key.length || key.length <= 0) {
                        return obj;
                    }

                    if (index === error.propertyDepth.length - 1 && obj[key]) {
                        delete obj[key];
                    }

                    return (obj !== null && obj[key]) ? obj[key] : null;
                }, state[error.module]);
            }

            return true;
        }
    }
});
