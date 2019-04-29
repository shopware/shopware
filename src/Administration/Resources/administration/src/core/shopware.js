// Necessary cause the unit test aren't having the shopware module globally available.
let Shopware = global.Shopware;

try {
    Shopware = window.Shopware;
} catch (err) {} // eslint-disable-line

if (Shopware === undefined) {
    Shopware = require('src/core/common.js'); // eslint-disable-line
}

export const Module = {
    register: Shopware.Module.register,
    getModuleRegistry: Shopware.Module.getModuleRegistry,
    getModuleRoutes: Shopware.Module.getModuleRoutes,
    getModuleByEntityName: Shopware.Module.getModuleByEntityName
};

export const Component = {
    register: Shopware.Component.register,
    extend: Shopware.Component.extend,
    override: Shopware.Component.override,
    build: Shopware.Component.build,
    getTemplate: Shopware.Component.getTemplate,
    getComponentRegistry: Shopware.Component.getComponentRegistry
};

export const Template = {
    register: Shopware.Template.register,
    extend: Shopware.Template.extend,
    override: Shopware.Template.override,
    getRenderedTemplate: Shopware.Template.getRenderedTemplate,
    find: Shopware.Template.find,
    findOverride: Shopware.Template.findOverride
};

export const Application = Shopware.Application;

export const State = {
    registerStore: Shopware.State.registerStore,
    getStore: Shopware.State.getStore,
    getStoreRegistry: Shopware.State.getStoreRegistry
};

export const Mixin = {
    register: Shopware.Mixin.register,
    getByName: Shopware.Mixin.getByName
};

export const Filter = {
    register: Shopware.Filter.register,
    getByName: Shopware.Filter.getByName
};

export const Directive = {
    register: Shopware.Directive.register,
    getByName: Shopware.Directive.getByName
};

export const Locale = {
    register: Shopware.Locale.register,
    getByName: Shopware.Locale.getByName,
    extend: Shopware.Locale.extend
};

export const Entity = {
    addDefinition: Shopware.Entity.addDefinition,
    getDefinition: Shopware.Entity.getDefinition,
    getDefinitionRegistry: Shopware.Entity.getDefinitionRegistry,
    getRawEntityObject: Shopware.Entity.getRawEntityObject,
    getPropertyBlacklist: Shopware.Entity.getPropertyBlacklist,
    getRequiredProperties: Shopware.Entity.getRequiredProperties,
    getAssociatedProperties: Shopware.Entity.getAssociatedProperties,
    getTranslatableProperties: Shopware.Entity.getTranslatableProperties
};

export const ApiService = {
    register: Shopware.ApiService.register,
    getByName: Shopware.ApiService.getByName,
    getRegistry: Shopware.ApiService.getRegistry,
    getServices: Shopware.ApiService.getServices,
    has: Shopware.ApiService.has
};

export const Defaults = {
    systemLanguageId: '2fbb5fe2e29a4d70aa5854ce7ce3e20b',
    defaultLanguageIds: ['2fbb5fe2e29a4d70aa5854ce7ce3e20b', '00e84bd18c574a6ca748ac0db17654dc'],
    versionId: '0fa91ce3e96a4bc2be4bd9ce752c3425'
};

export default {
    Module,
    Component,
    Template,
    Application,
    State,
    Mixin,
    Entity,
    Filter,
    Directive,
    Locale,
    ApiService,
    Defaults
};
