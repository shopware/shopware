
const observationApiService = {
    setReactive(object, prop, descriptor) {
        object[prop] = descriptor;
    },

    deleteReactive(object, prop) {
        delete object[prop];
    }
};

function setObservationApiFunctions(setReactive, deleteReactive) {
    if (typeof setReactive !== 'function') {
        throw new Error(
            '[ObservationApiService] setReactive must be a function with signature' +
            '(object: Object|Array, key: string, value: mixed)'
        );
    }

    if (typeof deleteReactive !== 'function') {
        throw new Error(
            '[ObservationApiService] deleteReactive must be a function with signature' +
            '(object: Object|Array, key: string)'
        );
    }

    observationApiService.setReactive = setReactive;
    observationApiService.deleteReactive = deleteReactive;
}

export default {
    setReactive(object, prop, descriptor) {
        return observationApiService.setReactive(object, prop, descriptor);
    },

    deleteReactive(object, prop) {
        return observationApiService.deleteReactive(object, prop);
    },

    setObservationApiFunctions
};
