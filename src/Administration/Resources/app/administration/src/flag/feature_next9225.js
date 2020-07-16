export const NEXT9225 = 'next9225';
export default {
    next9225,
    ifNext9225,
    ifNext9225Call,
    NEXT9225
};

export function next9225() {
    return Shopware.FeatureConfig.isActive('next9225');
}

export function ifNext9225(closure) {
    if (next9225()) {
        closure();
    }
}

export function ifNext9225Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext9225(closure);
}
