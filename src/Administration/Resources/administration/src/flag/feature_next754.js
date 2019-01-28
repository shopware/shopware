export const NEXT754 = 'next754';
export default {
    next754,
    ifNext754,
    ifNext754Call,
    NEXT754
};

export function next754() {
    return Shopware.FeatureConfig.isActive('next754');
}

export function ifNext754(closure) {
    if (next754()) {
        closure();
    }
}

export function ifNext754Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext754(closure);
}
  