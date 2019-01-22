export const NEXT1387 = 'next1387';
export default {
    next1387,
    ifNext1387,
    ifNext1387Call,
    NEXT1387
};

export function next1387() {
    return Shopware.FeatureConfig.isActive('next1387');
}

export function ifNext1387(closure) {
    if (next1387()) {
        closure();
    }
}

export function ifNext1387Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext1387(closure);
}
  