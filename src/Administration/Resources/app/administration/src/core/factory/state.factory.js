export default () => {
    const State = function State() {};

    const PrivateState = function PrivateState() {
        this._registerProperty = function registerProperty(name, property) {
            Object.defineProperty(this, name, {
                value: property,
                writable: false,
                enumerable: true,
                configurable: true,
            });

            return this;
        };

        this._registerPrivateProperty = this._registerProperty.bind(this);

        this._registerGetterMethod = function registerGetterMethod(name, getMethod, setMethod) {
            Object.defineProperty(this, name, {
                get: getMethod,
                set: setMethod,
                enumerable: true,
                configurable: true,
            });

            return this;
        };
    };

    State.prototype = new PrivateState();

    return new State();
};
