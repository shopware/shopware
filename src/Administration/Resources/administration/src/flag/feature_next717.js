export default {
    next717,
    ifNext717,
    ifNext717Call,
    NEXT717
};

export const NEXT717 = 'next717';

export function next717() {
    return Shopware.FeatureConfig.isActive('next717');
}

export function ifNext717(closure) {
    if (next717()) {
        closure();
    }
}

export function ifNext717Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext717(closure);
}
