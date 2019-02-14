export const NEXT134 = 'next134';
export default {
    next134,
    ifNext134,
    ifNext134Call,
    NEXT134
};

export function next134() {
    return Shopware.FeatureConfig.isActive('next134');
}

export function ifNext134(closure) {
    if (next134()) {
        closure();
    }
}

export function ifNext134Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext134(closure);
}
  