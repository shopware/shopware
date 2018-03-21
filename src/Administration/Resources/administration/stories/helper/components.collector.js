import Vue from 'vue';
import VueX from 'vuex';
import storeDefinition from 'src/app/store';

require('../../src/core/common.js');
require('../../src/app/mixin/index.js');
require('../../src/app/filter/index.js');
require('../../src/app/component/components');
require('../../src/app/state');

const factoryContainer = Shopware.Application.getContainer('factory');
const componentFactory = factoryContainer.component;
const stateFactory = factoryContainer.state;

const componentRegistry = componentFactory.getComponentRegistry();

Vue.use(VueX);

const store = new VueX.Store(storeDefinition);

stateFactory.getStateRegistry().forEach((stateModule, name) => {
    store.registerModule(name, stateModule);
});

Vue.filter('asset', (value) => {
    if (!value) {
        return '';
    }

    return value;
});

// Overrides icon path
Shopware.Component.override('sw-icon', {
    computed: {
        iconSetPath() {
            return `img/sw-icons.svg#${this.iconNamePrefix + this.name}`;
        }
    }
});

const vueComponents = new Map();
componentRegistry.forEach((component) => {
    const componentName = component.name;
    const componentConfig = Shopware.Component.build(componentName);

    if (!componentConfig) {
        return;
    }

    componentConfig.store = store;

    const builtComponent = Vue.component(componentName, componentConfig);
    vueComponents.set(componentName, builtComponent);
});

export default vueComponents;
