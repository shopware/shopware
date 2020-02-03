export const NEXT6009 = 'next6009';
export default {
    next6009,
    ifNext6009,
    ifNext6009Call,
    NEXT6009
};

export function next6009() {
    return Shopware.FeatureConfig.isActive('next6009');
}

export function ifNext6009(closure) {
    if (next6009()) {
        closure();
    }
}

export function ifNext6009Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext6009(closure);
}
