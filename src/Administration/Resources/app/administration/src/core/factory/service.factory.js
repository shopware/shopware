export default (() => {
    return function Service(serviceName) {
        this.get = (name) => Shopware.Application.getContainer('service')[name];
        this.list = () => Shopware.Application.getContainer('service').$list();
        this.register = (name, service) => Shopware.Application.addServiceProvider(name, service);
        this.registerMiddleware = (...args) => Shopware.Application.addServiceProviderMiddleware(...args);
        this.registerDecorator = (...args) => Shopware.Application.addServiceProviderDecorator(...args);

        return serviceName ? this.get(serviceName) : this;
    }.bind({});
})();
