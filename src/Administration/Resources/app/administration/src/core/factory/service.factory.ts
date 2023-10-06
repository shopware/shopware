/**
 * @package admin
 */

type ServiceObject = {
    get: <SN extends keyof ServiceContainer>(serviceName: SN) => ServiceContainer[SN],
    list: () => (keyof ServiceContainer)[],
    register: <SN extends keyof ServiceContainer>(serviceName: SN, service: ServiceContainer[SN]) => void,
    registerMiddleware: typeof Shopware.Application.addServiceProviderMiddleware,
    registerDecorator: typeof Shopware.Application.addServiceProviderDecorator,
}

/**
 * Return the ServiceObject (Shopware.Service().myService)
 * or direct access the services (Shopware.Service('myService')
 */
function serviceAccessor<SN extends keyof ServiceContainer>(serviceName: SN): ServiceContainer[SN]
function serviceAccessor(): ServiceObject
function serviceAccessor<SN extends keyof ServiceContainer>(serviceName?: SN): ServiceContainer[SN] | ServiceObject {
    if (serviceName) {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        return Shopware.Application.getContainer('service')[serviceName];
    }

    const serviceObject: ServiceObject = {
        // eslint-disable-next-line @typescript-eslint/no-unsafe-return
        get: (name) => Shopware.Application.getContainer('service')[name],
        list: () => Shopware.Application.getContainer('service').$list(),
        register: (name, service) => Shopware.Application.addServiceProvider(name, service),
        registerMiddleware: (...args) => Shopware.Application.addServiceProviderMiddleware(...args),
        registerDecorator: (...args) => Shopware.Application.addServiceProviderDecorator(...args),
    };

    return serviceObject;
}

// eslint-disable-next-line sw-deprecation-rules/private-feature-declarations
export default serviceAccessor;
