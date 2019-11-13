export const NEXT3722 = 'next3722';
export default {
    next3722,
    ifNext3722,
    ifNext3722Call,
    NEXT3722
};

export function next3722() {
    return Shopware.FeatureConfig.isActive('next3722');
}

export function ifNext3722(closure) {
    if (next3722()) {
        closure();
    }
}

export function ifNext3722Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext3722(closure);
}
  