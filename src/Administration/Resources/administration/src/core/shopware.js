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
    mapActions: Shopware.State.mapActions,
    mapState: Shopware.State.mapState,
    mapMutations: Shopware.State.mapMutations,
    mapGetters: Shopware.State.mapGetters,
    register: Shopware.State.register
};

export default {
    Module,
    Component,
    Template,
    Application,
    State
};
