export const Module = {
    register: Shopware.Module.register
};

export const Component = {
    register: Shopware.Component.register,
    extend: Shopware.Component.extend,
    override: Shopware.Component.override,
    build: Shopware.Component.build,
    getTemplate: Shopware.Component.getTemplate
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
    register: Shopware.State.register
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

export const Entity = {
    addDefinition: Shopware.Entity.addDefinition,
    getDefinition: Shopware.Entity.getDefinition,
    getDefinitionRegistry: Shopware.Entity.getDefinitionRegistry,
    getRawEntityObject: Shopware.Entity.getRawEntityObject,
    getRequiredProperties: Shopware.Entity.getRequiredProperties
};

export default {
    Module,
    Component,
    Template,
    Application,
    State,
    Mixin,
    Entity,
    Filter
};
