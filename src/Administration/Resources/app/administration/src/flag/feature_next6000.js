export const NEXT6000 = 'next6000';
export default {
    next6000,
    ifNext6000,
    ifNext6000Call,
    NEXT6000
};

export function next6000() {
    return Shopware.FeatureConfig.isActive('next6000');
}

export function ifNext6000(closure) {
    if (next6000()) {
        closure();
    }
}

export function ifNext6000Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext6000(closure);
}
