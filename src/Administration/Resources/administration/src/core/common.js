/* eslint-disable */
const Bottle = require('bottlejs');

const ModuleFactory = require('src/core/factory/module.factory').default;
const ComponentFactory = require('src/core/factory/component.factory').default;
const TemplateFactory = require('src/core/factory/template.factory').default;
const StateFactory = require('src/core/factory/state.factory').default;

const utils = require('src/core/service/util.service').default;
const ApplicationBootstrapper = require('src/core/application').default;

const container = new Bottle({
    strict: true
});

const application = new ApplicationBootstrapper(container);

application
    .addFactory('vue', () => {
        return VueJS.default;
    })
    .addFactory('component', () => {
        return ComponentFactory;
    })
    .addFactory('template', () => {
        return TemplateFactory;
    })
    .addFactory('module', () => {
        return ModuleFactory;
    })
    .addFactory('state', () => {
        return StateFactory;
    });

const exposedInterface = {
    Module: {
        register: ModuleFactory.registerModule
    },
    Component: {
        register: ComponentFactory.register,
        extend: ComponentFactory.extend,
        override: ComponentFactory.override,
        build: ComponentFactory.build,
        getTemplate: ComponentFactory.getComponentTemplate
    },
    Template: {
        register: TemplateFactory.registerComponentTemplate,
        extend: TemplateFactory.extendComponentTemplate,
        override: TemplateFactory.registerTemplateOverride,
        getRenderedTemplate: TemplateFactory.getRenderedTemplate,
        find: TemplateFactory.findCustomTemplate,
        findOverride: TemplateFactory.findCustomTemplate
    },
    Utils: utils,
    Application: application,
    State: {
        mapActions: StateFactory.mapActions,
        mapState: StateFactory.mapState,
        mapMutations: StateFactory.mapMutations,
        mapGetters: StateFactory.mapGetters,
        register: StateFactory.registerStateModule
    }
};

module.exports = exposedInterface;
