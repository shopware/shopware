export default {
    next516,
    ifNext516,
    ifNext516Call,
    NEXT516
};

export const NEXT516 = 'next516';

export function next516() {
    return Shopware.FeatureConfig.isActive('next516');
}

export function ifNext516(closure) {
    if (next516()) {
        closure();
    }
}

export function ifNext516Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext516(closure);
}
  