/**
 * @sw-package framework
 *
 * The names services are registered and injected under.
 * Import a name from here instead of writing it, so a renamed service fails the build, not the page.
 * This file is the public API, also for extensions. The files in `./src` are private.
 */
/** @public */
export { repositoryFactory } from './src/service-names';
