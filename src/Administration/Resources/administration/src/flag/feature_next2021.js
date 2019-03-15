export const NEXT2021 = 'next2021';
export default {
    next2021,
    ifNext2021,
    ifNext2021Call,
    NEXT2021
};

export function next2021() {
    return Shopware.FeatureConfig.isActive('next2021');
}

export function ifNext2021(closure) {
    if (next2021()) {
        closure();
    }
}

export function ifNext2021Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext2021(closure);
}
