export const NEXT687 = 'next687';
export default {
    next687,
    ifNext687,
    ifNext687Call,
    NEXT687
};

export function next687() {
    return Shopware.FeatureConfig.isActive('next687');
}

export function ifNext687(closure) {
    if (next687()) {
        closure();
    }
}

export function ifNext687Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext687(closure);
}
