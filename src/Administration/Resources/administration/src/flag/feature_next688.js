export const NEXT688 = 'next688';
export default {
    next688,
    ifNext688,
    ifNext688Call,
    NEXT688
};

export function next688() {
    return Shopware.FeatureConfig.isActive('next688');
}

export function ifNext688(closure) {
    if (next688()) {
        closure();
    }
}

export function ifNext688Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext688(closure);
}
