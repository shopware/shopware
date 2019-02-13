export const NEXT1309 = 'next1309';
export default {
    next1309,
    ifNext1309,
    ifNext1309Call,
    NEXT1309
};

export function next1309() {
    return Shopware.FeatureConfig.isActive('next1309');
}

export function ifNext1309(closure) {
    if (next1309()) {
        closure();
    }
}

export function ifNext1309Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext1309(closure);
}
  