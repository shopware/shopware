// polyfills object.forEach
if (!Object.prototype.forEach) {
    Object.defineProperty(
        Object.prototype,
        'forEach',
        {
            enumerable: false,
            value: function (fn) {
                if (typeof fn !== 'function' && !fn.constructor.name) {
                    throw new TypeError('Argument is not a function');
                }

                for (const key in this) {
                    if (this.hasOwnProperty(key)) {
                        const yields = fn(this[key], key);

                        if (yields === false) {
                            break;
                        }
                    }
                }
            },
        },
    );
}
