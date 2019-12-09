export const NEXT5515 = 'next5515';
export default {
    next5515,
    ifNext5515,
    ifNext5515Call,
    NEXT5515
};

export function next5515() {
    return Shopware.FeatureConfig.isActive('next5515');
}

export function ifNext5515(closure) {
    if (next5515()) {
        closure();
    }
}

export function ifNext5515Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext5515(closure);
}
  