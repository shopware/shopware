export const NEXT6013 = 'next6013';
export default {
    next6013,
    ifNext6013,
    ifNext6013Call,
    NEXT6013
};

export function next6013() {
    return Shopware.FeatureConfig.isActive('next6013');
}

export function ifNext6013(closure) {
    if (next6013()) {
        closure();
    }
}

export function ifNext6013Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext6013(closure);
}
