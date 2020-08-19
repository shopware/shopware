export const NEXT10286 = 'next10286';
export default {
    next10286,
    ifNext10286,
    ifNext10286Call,
    NEXT10286
};

export function next10286() {
    return Shopware.FeatureConfig.isActive('next10286');
}

export function ifNext10286(closure) {
    if (next10286()) {
        closure();
    }
}

export function ifNext10286Call(object, methodName) {
    const closure = () => {
        object[methodName]();
    };

    ifNext10286(closure);
}
  