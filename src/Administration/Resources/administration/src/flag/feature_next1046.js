export const NEXT1046 = 'next1046';
export default {
    next1046,
    ifNext1046,
    ifNext1046Call,
    NEXT1046
};

export function next1046() {
    return Shopware.FeatureConfig.isActive('next1046');
}

export function ifNext1046(closure) {
    if (next1046()) {
        closure();
    }
}

export function ifNext1046Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext1046(closure);
}
