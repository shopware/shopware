export default () => {
    return Object.create({
        // TODO: add _store to prototype
        _store: undefined,

        _setStore(store) {
            this._store = store;
        },

        _getStore() {
            return this._store;
        },

        _registerProperty(name, property) {
            Object.defineProperty(this, name, {
                value: property,
                writable: false,
                enumerable: true,
                configurable: true
            });

            return this;
        }
    });
};
