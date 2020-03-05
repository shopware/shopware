export const NEXT6050 = 'next6050';
export default {
    next6050,
    ifNext6050,
    ifNext6050Call,
    NEXT6050
};

export function next6050() {
    return Shopware.FeatureConfig.isActive('next6050');
}

export function ifNext6050(closure) {
    if (next6050()) {
        closure();
    }
}

export function ifNext6050Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext6050(closure);
}
