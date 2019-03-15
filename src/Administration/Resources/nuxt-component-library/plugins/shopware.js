import Vue from 'vue'; // eslint-disable-line import/no-extraneous-dependencies
import VueI18n from 'vue-i18n';
import enGBMessages from 'src/app/snippets/en-GB.json';
import DeviceHelper from 'src/core/plugins/device-helper.plugin';
import ValidationService from 'src/core/service/validation.service';

Vue.use(VueI18n);
Vue.use(DeviceHelper);

const Shopware = require('src/core/common');

global.Shopware = Shopware;
window.Shopware = Shopware;

const ContextFactory = require('src/core/factory/context.factory').default;

Shopware.Application.$container.factory('init.context', () => {
    return {};
});

Shopware.Application.$container.factory('init.contextService', (container) => {
    return ContextFactory(container.context);
});

Shopware.Application.$container.factory('service.validationService', () => {
    return ValidationService;
});
require('src/app/mixin/index');
require('src/app/directives/index');
require('src/app/filter/index');

function registerBaseComponents(baseComponents, componentFactory) {
    const filteredComponents = baseComponents.filter((item) => {
        return item !== undefined;
    });

    filteredComponents.forEach((component) => {
        const isExtendedComponent = (component.extendsFrom && component.extendsFrom.length);
        if (isExtendedComponent) {
            componentFactory.extend(component.name, component.extendsFrom, component);
            return;
        }
        componentFactory.register(component.name, component);
    });
}
registerBaseComponents(require('src/app/component/components').default, Shopware.Component);

const components = Shopware.Component.getComponentRegistry();
const factoryContainer = Shopware.Application.getContainer('factory');
const filterFactory = factoryContainer.filter;
const directiveFactory = factoryContainer.directive;

// Add filters
const filterRegistry = filterFactory.getRegistry();
filterRegistry.forEach((factoryMethod, name) => {
    Vue.filter(name, factoryMethod);
});

// Add directives
const directiveRegistry = directiveFactory.getDirectiveRegistry();
directiveRegistry.forEach((directive, name) => {
    Vue.directive(name, directive);
});

// Add components
const vueComponents = {};
components.forEach((config) => {
    const componentName = config.name;
    const Component = Shopware.Component;
    const Mixin = Shopware.Mixin;
    const componentConfig = Component.build(componentName);

    if (!componentConfig) {
        return false;
    }

    // If the mixin is a string, use our mixin registry
    if (componentConfig.mixins && componentConfig.mixins.length) {
        componentConfig.mixins = componentConfig.mixins.map((mixin) => {
            if (typeof mixin === 'string') {
                return Mixin.getByName(mixin);
            }

            return mixin;
        });
    }

    const vueComponent = Vue.component(componentName, componentConfig);
    vueComponents[componentName] = vueComponent;

    return vueComponent;
});

export default ({ app }) => {
    // Apply translations to application
    const messages = { 'en-GB': enGBMessages };
    app.provide = () => {
        return Shopware.Application.getContainer('service');
    };
    app.i18n = new VueI18n({
        locale: 'en-GB',
        fallbackLocale: 'en-GB',
        messages
    });
};
