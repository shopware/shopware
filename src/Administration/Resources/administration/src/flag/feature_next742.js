export const NEXT742 = 'next742';
export default {
    next742,
    ifNext742,
    ifNext742Call,
    NEXT742
};

export function next742() {
    return Shopware.FeatureConfig.isActive('next742');
}

export function ifNext742(closure) {
    if (next742()) {
        closure();
    }
}

export function ifNext742Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext742(closure);
}
