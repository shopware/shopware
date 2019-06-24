export const NEXT749 = 'next749';
export default {
    next749,
    ifNext749,
    ifNext749Call,
    NEXT749
};

export function next749() {
    return Shopware.FeatureConfig.isActive('next749');
}

export function ifNext749(closure) {
    if (next749()) {
        closure();
    }
}

export function ifNext749Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext749(closure);
}
