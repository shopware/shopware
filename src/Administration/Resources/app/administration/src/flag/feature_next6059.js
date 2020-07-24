export const NEXT6059 = 'next6059';
export default {
    next6059,
    ifNext6059,
    ifNext6059Call,
    NEXT6059
};

export function next6059() {
    return Shopware.FeatureConfig.isActive('next6059');
}

export function ifNext6059(closure) {
    if (next6059()) {
        closure();
    }
}

export function ifNext6059Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext6059(closure);
}
  