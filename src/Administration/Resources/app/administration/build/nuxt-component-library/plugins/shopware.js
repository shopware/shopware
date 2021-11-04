import Vue from 'vue'; // eslint-disable-line import/no-extraneous-dependencies
import Vuex from 'vuex';
import VueI18n from 'vue-i18n';
import enGBMessages from 'src/app/snippet/en-GB.json';

const Shopware = require('src/core/shopware').default;

// Expose shopware object globally
global.Shopware = Shopware;
window.Shopware = Shopware;

const DeviceHelper = require('src/app/plugin/device-helper.plugin').default;
const ValidationService = require('src/core/service/validation.service').default;
const iconComponents = require('src/app/assets/icons/icons').default;
const VuexModules = require('src/app/state/index').default;
const ShortcutService = require('src/app/service/shortcut.service').default;

require('src/app/mixin/index').default();
require('src/app/directive/index').default();
require('src/app/filter/index').default();
require('src/app/init/component-helper.init').default();
require('src/app/init-pre/state.init').default();

// Vue plugins
Vue.use(VueI18n);
Vue.use(DeviceHelper);
Vue.use(Vuex);

// Expose shopware object globally
global.Shopware = Shopware;
window.Shopware = Shopware;

Shopware.Application.$container.factory('init.context', () => {
    return {};
});

Shopware.Application.$container.factory('service.validationService', () => {
    return ValidationService;
});

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
registerBaseComponents(require('src/app/component/components').default(), Shopware.Component);

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

const iconNames = [];
iconComponents.forEach((component) => {
    Shopware.Component.register(component.name, component);
    iconNames.push(component.name);
});

Shopware.Application
    .addServiceProvider('iconNames', () => {
        return iconNames;
    })
    .addServiceProvider('shortcutService', () => {
        return ShortcutService(factoryContainer.shortcut);
    });

Shopware.Component.override('sw-error', {
    computed: {
        imagePath() {
            return './administration/static/img/error.svg';
        }
    }
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
