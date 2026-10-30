/**
 * @sw-package framework
 */

import VueAdapter from 'src/app/adapter/view/vue.adapter';
import { createApplication, prepareVueAdapterEnvironment, resetVueAdapterTestState } from './vue-adapter-test-setup';

describe('ASYNC app/adapter/view/vue.adapter.js - route guards', () => {
    let vueAdapter;

    beforeEach(() => {
        prepareVueAdapterEnvironment();
        vueAdapter = new VueAdapter(createApplication());
    });

    afterEach(() => {
        resetVueAdapterTestState();
    });

    it('should compose route guards from extends, mixins and component config for route-loaded components', async () => {
        const guardOrder = {
            enter: [],
            update: [],
            leave: [],
        };
        const guardContext = {
            update: [],
            leave: [],
        };
        const enterCallbacks = [];
        const suffix = Shopware.Utils.createId();
        const firstMixinName = `route-guard-first-${suffix}`;
        const secondMixinName = `route-guard-second-${suffix}`;
        const baseComponentName = `route-guard-base-${suffix}`;
        const componentName = `route-guard-component-${suffix}`;

        Shopware.Mixin.register(firstMixinName, {
            beforeRouteEnter(to, from, next) {
                guardOrder.enter.push('first-mixin');
                next((vm) => enterCallbacks.push(`first-mixin:${vm.id}`));
            },
            beforeRouteUpdate(to, from, next) {
                guardOrder.update.push('first-mixin');
                guardContext.update.push(this.id);
                next();
            },
            beforeRouteLeave(to, from, next) {
                guardOrder.leave.push('first-mixin');
                guardContext.leave.push(this.id);
                next();
            },
        });

        Shopware.Mixin.register(secondMixinName, {
            beforeRouteEnter(to, from, next) {
                guardOrder.enter.push('second-mixin');
                next((vm) => enterCallbacks.push(`second-mixin:${vm.id}`));
            },
            beforeRouteUpdate(to, from, next) {
                guardOrder.update.push('second-mixin');
                guardContext.update.push(this.id);
                next();
            },
            beforeRouteLeave(to, from, next) {
                guardOrder.leave.push('second-mixin');
                guardContext.leave.push(this.id);
                next();
            },
        });

        Shopware.Component.register(baseComponentName, {
            template: '<div></div>',
            name: baseComponentName,
            beforeRouteEnter(to, from, next) {
                guardOrder.enter.push('base-component');
                next((vm) => enterCallbacks.push(`base-component:${vm.id}`));
            },
            beforeRouteUpdate(to, from, next) {
                guardOrder.update.push('base-component');
                guardContext.update.push(this.id);
                next();
            },
            beforeRouteLeave(to, from, next) {
                guardOrder.leave.push('base-component');
                guardContext.leave.push(this.id);
                next();
            },
        });

        Shopware.Component.extend(componentName, baseComponentName, {
            template: '<div></div>',
            name: componentName,
            mixins: [firstMixinName, secondMixinName],
            beforeRouteEnter(to, from, next) {
                guardOrder.enter.push('component');
                next((vm) => enterCallbacks.push(`component:${vm.id}`));
            },
            beforeRouteUpdate(to, from, next) {
                guardOrder.update.push('component');
                guardContext.update.push(this.id);
                next();
            },
            beforeRouteLeave(to, from, next) {
                guardOrder.leave.push('component');
                guardContext.leave.push(this.id);
                next();
            },
        });

        const routeComponent = await vueAdapter.getComponentForRoute(componentName)();

        expect(routeComponent).not.toBe(false);

        let enterGuardCallback;
        await routeComponent.beforeRouteEnter({}, {}, (callback) => {
            enterGuardCallback = callback;
        });

        expect(guardOrder.enter).toEqual([
            'base-component',
            'first-mixin',
            'second-mixin',
            'component',
        ]);
        expect(typeof enterGuardCallback).toBe('function');

        enterGuardCallback({ id: 'vm-instance' });

        expect(enterCallbacks).toEqual([
            'base-component:vm-instance',
            'first-mixin:vm-instance',
            'second-mixin:vm-instance',
            'component:vm-instance',
        ]);

        const routeGuardVm = { id: 'route-vm' };

        await routeComponent.beforeRouteUpdate.call(routeGuardVm, {}, {}, jest.fn());

        expect(guardOrder.update).toEqual([
            'base-component',
            'first-mixin',
            'second-mixin',
            'component',
        ]);
        expect(guardContext.update).toEqual([
            'route-vm',
            'route-vm',
            'route-vm',
            'route-vm',
        ]);

        await routeComponent.beforeRouteLeave.call(routeGuardVm, {}, {}, jest.fn());

        expect(guardOrder.leave).toEqual([
            'base-component',
            'first-mixin',
            'second-mixin',
            'component',
        ]);
        expect(guardContext.leave).toEqual([
            'route-vm',
            'route-vm',
            'route-vm',
            'route-vm',
        ]);
    });

    it('should ignore synchronous errors thrown after next() in callback-style route guards', async () => {
        const suffix = Shopware.Utils.createId();
        const componentName = `route-guard-post-next-throw-${suffix}`;

        Shopware.Component.register(componentName, {
            template: '<div></div>',
            name: componentName,
            beforeRouteUpdate(to, from, next) {
                next();
                throw new Error('post-next cleanup failed');
            },
        });

        const routeComponent = await vueAdapter.getComponentForRoute(componentName)();

        expect(routeComponent).not.toBe(false);

        const next = jest.fn();

        await expect(routeComponent.beforeRouteUpdate.call({ id: 'route-vm' }, {}, {}, next)).resolves.toBeUndefined();

        expect(next).toHaveBeenCalledTimes(1);
        expect(next).toHaveBeenCalledWith();
    });
});
