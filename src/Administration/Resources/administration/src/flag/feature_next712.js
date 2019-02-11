export const NEXT712 = 'next712';
export default {
    next712,
    ifNext712,
    ifNext712Call,
    NEXT712
};

export function next712() {
    return Shopware.FeatureConfig.isActive('next712');
}

export function ifNext712(closure) {
    if (next712()) {
        closure();
    }
}

export function ifNext712Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext712(closure);
}
  