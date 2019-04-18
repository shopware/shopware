export const NEXT685 = 'next685';
export default {
    next685,
    ifNext685,
    ifNext685Call,
    NEXT685
};

export function next685() {
    return Shopware.FeatureConfig.isActive('next685');
}

export function ifNext685(closure) {
    if (next685()) {
        closure();
    }
}

export function ifNext685Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext685(closure);
}
