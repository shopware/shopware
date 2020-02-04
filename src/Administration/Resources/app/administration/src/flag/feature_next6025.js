export const NEXT6025 = 'next6025';
export default {
    next6025,
    ifNext6025,
    ifNext6025Call,
    NEXT6025
};

export function next6025() {
    return Shopware.FeatureConfig.isActive('next6025');
}

export function ifNext6025(closure) {
    if (next6025()) {
        closure();
    }
}

export function ifNext6025Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext6025(closure);
}
