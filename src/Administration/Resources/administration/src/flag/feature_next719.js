export default {
    next719,
    ifNext719,
    ifNext719Call,
    NEXT719
};

export const NEXT719 = 'next719';

export function next719() {
    return Shopware.FeatureConfig.isActive('next719');
}

export function ifNext719(closure) {
    if (next719()) {
        closure();
    }
}

export function ifNext719Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext719(closure);
}
  