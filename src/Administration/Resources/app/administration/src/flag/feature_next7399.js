export const NEXT7399 = 'next7399';
export default {
    next7399,
    ifNext7399,
    ifNext7399Call,
    NEXT7399
};

export function next7399() {
    return Shopware.FeatureConfig.isActive('next7399');
}

export function ifNext7399(closure) {
    if (next7399()) {
        closure();
    }
}

export function ifNext7399Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext7399(closure);
}
