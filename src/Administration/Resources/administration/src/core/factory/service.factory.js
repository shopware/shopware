export default (application) => {
    return function Service(serviceName) {
        this.get = (name) => application.getContainer('service')[name];
        this.list = () => application.getContainer('service').$list();
        this.register = (name, service) => application.addServiceProvider(name, service);
        this.registerMiddleware = (...args) => application.addServiceProviderMiddleware(...args);
        this.registerDecorator = (...args) => application.addServiceProviderDecorator(...args);

        return serviceName ? this.get(serviceName) : this;
    }.bind({});
};
