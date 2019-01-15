export const NEXT739 = 'next739';
export default {
    next739,
    ifNext739,
    ifNext739Call,
    NEXT739
};

export function next739() {
    return Shopware.FeatureConfig.isActive('next739');
}

export function ifNext739(closure) {
    if (next739()) {
        closure();
    }
}

export function ifNext739Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext739(closure);
}
  