export const NEXT1567 = 'next1567';
export default {
    next1567,
    ifNext1567,
    ifNext1567Call,
    NEXT1567
};

export function next1567() {
    return Shopware.FeatureConfig.isActive('next1567');
}

export function ifNext1567(closure) {
    if (next1567()) {
        closure();
    }
}

export function ifNext1567Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext1567(closure);
}
  