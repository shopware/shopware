import Vue from 'vue';

require('../../src/core/common.js');
require('../../src/app/component/components');

const factoryContainer = Shopware.Application.getContainer('factory');
const componentFactory = factoryContainer.component;

const componentRegistry = componentFactory.getComponentRegistry();

Vue.filter('asset', (value) => {
    if (!value) {
        return '';
    }

    return value;
});

const vueComponents = new Map();
componentRegistry.forEach((component) => {
    const componentName = component.name;
    const componentConfig = Shopware.Component.build(componentName);

    if (!componentConfig) {
        return;
    }

    const builtComponent = Vue.component(componentName, componentConfig);
    vueComponents.set(componentName, builtComponent);
});

export default vueComponents;
