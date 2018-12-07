export default {
    next1207,
    ifNext1207,
    ifNext1207Call,
    NEXT1207
};

export const NEXT1207 = 'next1207';

export function next1207() {
    return Shopware.FeatureConfig.isActive('next1207');
}

export function ifNext1207(closure) {
    if (next1207()) {
        closure();
    }
}

export function ifNext1207Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext1207(closure);
}
  