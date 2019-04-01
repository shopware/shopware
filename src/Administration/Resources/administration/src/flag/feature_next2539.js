export const NEXT2539 = 'next2539';
export default {
    next2539,
    ifNext2539,
    ifNext2539Call,
    NEXT2539
};

export function next2539() {
    return Shopware.FeatureConfig.isActive('next2539');
}

export function ifNext2539(closure) {
    if (next2539()) {
        closure();
    }
}

export function ifNext2539Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext2539(closure);
}
