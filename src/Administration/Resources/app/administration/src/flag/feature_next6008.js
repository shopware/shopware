export const NEXT6008 = 'next6008';
export default {
    next6008,
    ifNext6008,
    ifNext6008Call,
    NEXT6008
};

export function next6008() {
    return Shopware.FeatureConfig.isActive('next6008');
}

export function ifNext6008(closure) {
    if (next6008()) {
        closure();
    }
}

export function ifNext6008Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext6008(closure);
}
