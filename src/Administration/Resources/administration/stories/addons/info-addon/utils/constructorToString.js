function constructorToString(constructor) {
    if (constructor instanceof Array) {
        return constructor.map(constructorToString).join(' | ');
    }
    else if (constructor === Number) {
        return 'Number';
    }
    else if (constructor === String) {
        return 'String';
    }
    else if (constructor === Object) {
        return 'Object';
    }
    else if (constructor === Boolean) {
        return 'Boolean';
    }
    else if (constructor === Function) {
        return 'Function';
    }
    else if (constructor === Date) {
        return 'Date';
    }
    else if (constructor === Symbol) {
        return 'Symbol';
    }
    else if (constructor === Array) {
        return 'Array';
    }
    else {
        return 'Unknown';
    }
};

export default constructorToString;
