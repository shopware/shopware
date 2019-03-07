export const NEXT741 = 'next741';
export default {
    next741,
    ifNext741,
    ifNext741Call,
    NEXT741
};

export function next741() {
    return Shopware.FeatureConfig.isActive('next741');
}

export function ifNext741(closure) {
    if (next741()) {
        closure();
    }
}

export function ifNext741Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext741(closure);
}
