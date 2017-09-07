export default class ShopwareApplication {
    addInitializer(initializer) {
        this.initializers = this.initializers || [];
        this.initializers.push(initializer);

        return this;
    }

    addProvider(name, provider) {
        this.providers = this.providers || {};
        this.providers[name] = provider;

        return this;
    }

    addProviderDecorator(name, decorator) {
        this.providers = this.providers || {};
        this.providers[name] = decorator;

        return this;
    }

    start(context = {}) {
        this.runInitializers(context).then(this.createApplicationRoot.bind(this));
    }

    runInitializers(context) {
        const applicationInstance = this;

        return this.initializers.reduce((promise, initializer) => {
            return promise.then((configuration) => {
                return new Promise((resolve) => {
                    initializer(applicationInstance, configuration, resolve, context);
                });
            });
        }, Promise.resolve([]));
    }

    createApplicationRoot(configuration) {
        const me = this;

        me.applicationRoot = configuration.view.createInstance('#app', configuration.router, me.providers);

        return this;
    }
}
