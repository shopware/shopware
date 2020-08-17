export const NEXT6010 = 'next6010';
export default {
    next6010,
    ifNext6010,
    ifNext6010Call,
    NEXT6010
};

export function next6010() {
    return Shopware.FeatureConfig.isActive('next6010');
}

export function ifNext6010(closure) {
    if (next6010()) {
        closure();
    }
}

export function ifNext6010Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext6010(closure);
}
  