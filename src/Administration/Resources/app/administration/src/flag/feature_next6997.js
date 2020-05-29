export const NEXT6997 = 'next6997';
export default {
    next6997,
    ifNext6997,
    ifNext6997Call,
    NEXT6997
};

export function next6997() {
    return Shopware.FeatureConfig.isActive('next6997');
}

export function ifNext6997(closure) {
    if (next6997()) {
        closure();
    }
}

export function ifNext6997Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext6997(closure);
}
  