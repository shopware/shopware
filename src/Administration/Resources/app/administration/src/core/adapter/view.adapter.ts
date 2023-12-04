/**
 * @package admin
 */

import type { FullState } from 'src/core/factory/state.factory';
import type { App } from 'vue';
import type { Router } from 'vue-router';
import type VueRouterV2 from 'vue-router_v2';
import type AsyncComponentFactory from 'src/core/factory/async-component.factory';
import type { ComponentConfig } from 'src/core/factory/async-component.factory';
import type ApplicationBootstrapper from 'src/core/application';
import type LocaleFactory from 'src/core/factory/locale.factory';
import type Vue2 from 'vue_v2';

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

    public root: App<Element> | Vue2 | null;

    public router: Router | VueRouterV2 | undefined;

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
    abstract init(renderElement: string, router: Router, providers: { [key: string]: unknown }): App | null;

    /**
     * Initializes all core components as Vue components.
     */
    abstract initComponents(renderElement: string, router: Router, providers: unknown[]): void

    abstract initDependencies(): void

    /**
     * Returns the component as a Vue component.
     * Includes the full rendered template with all overrides.
     */
    abstract createComponent(componentName: string): Promise<App<Element> | Vue2>

    /**
     * Returns a final Vue component by its name.
     */
    abstract getComponent(componentName: string): App<Element> | Vue2 | null

    /**
     * Returns a final Vue component by its name without defineAsyncComponent
     * which cannot be used in the router.
     */
    abstract getComponentForRoute(componentName: string): () => (
        Promise<boolean | ComponentConfig> | App<Element> | undefined
    )

    /**
     * Returns the complete set of available Vue components.
     */
    abstract getComponents(): { [componentName: string]: App<Element> }

    /**
     * Returns the adapter wrapper
     */
    abstract getWrapper(): App<Element> | typeof Vue2 | undefined

    /**
     * Returns the name of the adapter
     */
    abstract getName(): string

    /**
     * Returns the Vue.set function
     */
    abstract setReactive(target: unknown, propertyName: string, value: unknown): unknown

    /**
     * Returns the Vue.delete function
     */
    abstract deleteReactive(target: unknown, propertyName: string): void
}
