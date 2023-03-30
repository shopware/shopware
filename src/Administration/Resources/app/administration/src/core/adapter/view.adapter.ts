/**
 * @package admin
 */

import type VueRouter from 'vue-router';
import type { FullState } from 'src/core/factory/state.factory';
import type { VueConstructor } from 'vue';
import type Vue from 'vue';
import type AsyncComponentFactory from 'src/core/factory/async-component.factory';
import type ApplicationBootstrapper from 'src/core/application';
import type LocaleFactory from 'src/core/factory/locale.factory';

/**
 * @deprecated tag:v6.6.0 - Will be private
 * View Adapter Boilerplate class which provides a blueprint for view adapters (like for React, VueJS, ...)
 */
export default abstract class ViewAdapter {
    public Application: ApplicationBootstrapper;

    public applicationFactory: FactoryContainer;

    public componentFactory: typeof AsyncComponentFactory;

    public stateFactory: () => FullState;

    public localeFactory: typeof LocaleFactory;

    public root: Vue | null;

    public router: VueRouter | undefined;

    /**
     * @constructor
     */
    constructor(Application: ApplicationBootstrapper) {
        this.Application = Application;
        this.applicationFactory = Application.getContainer('factory');

        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        this.componentFactory = this.applicationFactory.component;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        this.stateFactory = this.applicationFactory.state;
        // eslint-disable-next-line @typescript-eslint/no-unsafe-assignment
        this.localeFactory = this.applicationFactory.locale;
        this.root = null;
    }


    /**
     * Creates the main instance for the view layer.
     * Is used on startup process of the main application.
     */
    abstract init(renderElement: string, router: VueRouter, providers: { [key: string]: unknown }): Vue | null;

    /**
     * Initializes all core components as Vue components.
     */
    abstract initComponents(renderElement: string, router: VueRouter, providers: unknown[]): void

    abstract initDependencies(): void

    /**
     * Returns the component as a Vue component.
     * Includes the full rendered template with all overrides.
     */
    abstract createComponent(componentName: string): Promise<Vue>

    /**
     * Returns a final Vue component by its name.
     */
    abstract getComponent(componentName: string): Vue | null

    /**
     * Returns the complete set of available Vue components.
     */
    abstract getComponents(): { [componentName: string]: Vue }

    /**
     * Returns the adapter wrapper
     */
    abstract getWrapper(): VueConstructor<Vue>

    /**
     * Returns the name of the adapter
     */
    abstract getName(): string

    /**
     * Returns the Vue.set function
     */
    abstract setReactive(target: Vue, propertyName: string, value: unknown): unknown

    /**
     * Returns the Vue.delete function
     */
    abstract deleteReactive(target: Vue, propertyName: string): void
}
