/**
 * Shopware End Developer API
 * @module Shopware
 * @ignore
 */

// <reference path="types/common.d.ts" />
const Bottle = require('bottlejs');

const ModuleFactory = require('src/core/factory/module.factory').default;
const ComponentFactory = require('src/core/factory/component.factory').default;
const TemplateFactory = require('src/core/factory/template.factory').default;
const EntityFactory = require('src/core/factory/entity.factory').default;
const StateFactory = require('src/core/factory/state.factory').default;
const MixinFactory = require('src/core/factory/mixin.factory').default;
const FilterFactory = require('src/core/factory/filter.factory').default;
const DirectiveFactory = require('src/core/factory/directive.factory').default;

const utils = require('src/core/service/util.service').default;
const ApplicationBootstrapper = require('src/core/application').default;

const container = new Bottle({
    strict: true
});

const application = new ApplicationBootstrapper(container);

application
    .addFactory('component', () => {
        return ComponentFactory;
    })
    .addFactory('template', () => {
        return TemplateFactory;
    })
    .addFactory('module', () => {
        return ModuleFactory;
    })
    .addFactory('entity', () => {
        return EntityFactory;
    })
    .addFactory('state', () => {
        return StateFactory;
    })
    .addFactory('mixin', () => {
        return MixinFactory;
    })
    .addFactory('filter', () => {
        return FilterFactory;
    })
    .addFactory('directive', () => {
        return DirectiveFactory;
    });

module.exports = {
    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Module: {
        register: ModuleFactory.registerModule
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Component: {
        register: ComponentFactory.register,
        extend: ComponentFactory.extend,
        override: ComponentFactory.override,
        build: ComponentFactory.build,
        getTemplate: ComponentFactory.getComponentTemplate
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Template: {
        register: TemplateFactory.registerComponentTemplate,
        extend: TemplateFactory.extendComponentTemplate,
        override: TemplateFactory.registerTemplateOverride,
        getRenderedTemplate: TemplateFactory.getRenderedTemplate,
        find: TemplateFactory.findCustomTemplate,
        findOverride: TemplateFactory.findCustomTemplate
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Entity: {
        addDefinition: EntityFactory.addEntityDefinition,
        getDefinition: EntityFactory.getEntityDefinition,
        getDefinitionRegistry: EntityFactory.getDefinitionRegistry,
        getRawEntityObject: EntityFactory.getRawEntityObject,
        getRequiredProperties: EntityFactory.getRequiredProperties
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    State: {
        register: StateFactory.registerStateModule
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Mixin: {
        register: MixinFactory.register,
        getByName: MixinFactory.getByName
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Filter: {
        register: FilterFactory.register,
        getByName: FilterFactory.getByName
    },

    /**
     * @memberOf module:Shopware
     * @type {Object}
     */
    Directive: {
        register: DirectiveFactory.registerDirective,
        getByName: DirectiveFactory.getDirectiveByName
    },

    /**
     * @memberOf module:Shopware
     * @type {module:core/service/utils}
     */
    Utils: utils,

    /**
     * @memberOf module:Shopware
     * @type {module:core/application}
     */
    Application: application
};
