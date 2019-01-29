export const NEXT716 = 'next716';
export default {
    next716,
    ifNext716,
    ifNext716Call,
    NEXT716
};

export function next716() {
    return Shopware.FeatureConfig.isActive('next716');
}

export function ifNext716(closure) {
    if (next716()) {
        closure();
    }
}

export function ifNext716Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext716(closure);
}
  