export const NEXT733 = 'next733';
export default {
    next733,
    ifNext733,
    ifNext733Call,
    NEXT733
};

export function next733() {
    return Shopware.FeatureConfig.isActive('next733');
}

export function ifNext733(closure) {
    if (next733()) {
        closure();
    }
}

export function ifNext733Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext733(closure);
}
  