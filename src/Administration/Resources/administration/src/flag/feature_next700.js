export const NEXT700 = 'next700';
export default {
    next700,
    ifNext700,
    ifNext700Call,
    NEXT700
};

export function next700() {
    return Shopware.FeatureConfig.isActive('next700');
}

export function ifNext700(closure) {
    if (next700()) {
        closure();
    }
}

export function ifNext700Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext700(closure);
}
  