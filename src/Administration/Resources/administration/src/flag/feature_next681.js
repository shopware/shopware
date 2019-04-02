export const NEXT681 = 'next681';
export default {
    next681,
    ifNext681,
    ifNext681Call,
    NEXT681
};

export function next681() {
    return Shopware.FeatureConfig.isActive('next681');
}

export function ifNext681(closure) {
    if (next681()) {
        closure();
    }
}

export function ifNext681Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext681(closure);
}
