export const NEXT9278 = 'next9278';
export default {
    next9278,
    ifNext9278,
    ifNext9278Call,
    NEXT9278
};

export function next9278() {
    return Shopware.FeatureConfig.isActive('next9278');
}

export function ifNext9278(closure) {
    if (next9278()) {
        closure();
    }
}

export function ifNext9278Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext9278(closure);
}
