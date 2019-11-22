export const NEXT1797 = 'next1797';
export default {
    next1797,
    ifNext1797,
    ifNext1797Call,
    NEXT1797
};

export function next1797() {
    return Shopware.FeatureConfig.isActive('next1797');
}

export function ifNext1797(closure) {
    if (next1797()) {
        closure();
    }
}

export function ifNext1797Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext1797(closure);
}
  