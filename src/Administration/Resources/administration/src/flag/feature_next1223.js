export const NEXT1223 = 'next1223';
export default {
    next1223,
    ifNext1223,
    ifNext1223Call,
    NEXT1223
};

export function next1223() {
    return Shopware.FeatureConfig.isActive('next1223');
}

export function ifNext1223(closure) {
    if (next1223()) {
        closure();
    }
}

export function ifNext1223Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext1223(closure);
}
  