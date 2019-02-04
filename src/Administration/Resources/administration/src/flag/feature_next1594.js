export const NEXT1594 = 'next1594';
export default {
    next1594,
    ifNext1594,
    ifNext1594Call,
    NEXT1594
};

export function next1594() {
    return Shopware.FeatureConfig.isActive('next1594');
}

export function ifNext1594(closure) {
    if (next1594()) {
        closure();
    }
}

export function ifNext1594Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext1594(closure);
}
  