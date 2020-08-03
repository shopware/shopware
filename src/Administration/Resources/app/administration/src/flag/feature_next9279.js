export const NEXT9279 = 'next9279';
export default {
    next9279,
    ifNext9279,
    ifNext9279Call,
    NEXT9279
};

export function next9279() {
    return Shopware.FeatureConfig.isActive('next9279');
}

export function ifNext9279(closure) {
    if (next9279()) {
        closure();
    }
}

export function ifNext9279Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext9279(closure);
}
  