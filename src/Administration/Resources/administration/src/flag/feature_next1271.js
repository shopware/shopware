export const NEXT1271 = 'next1271';
export default {
    next1271,
    ifNext1271,
    ifNext1271Call,
    NEXT1271
};

export function next1271() {
    return Shopware.FeatureConfig.isActive('next1271');
}

export function ifNext1271(closure) {
    if (next1271()) {
        closure();
    }
}

export function ifNext1271Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext1271(closure);
}
  