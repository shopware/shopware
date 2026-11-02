/**
 * @sw-package discovery
 */

import { createWrapper, setupLandingPageTreeHooks } from './sw-landing-page-tree.fixtures';

describe('src/module/sw-category/component/sw-landing-page-tree', () => {
    setupLandingPageTreeHooks();

    it('should not be able to sort the items', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes().sortable).toBe('false');
    });

    it('should be able to delete the items in sw-tree', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete the items in sw-tree', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowDelete: false,
        });

        const tree = wrapper.find('.sw-tree');
        expect(tree.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should be able to create new landing pages', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('.sw-landing-page-tree__add-button-button');
        expect(treeItem.attributes().disabled).toBeUndefined();
    });

    it('should not be able to create new landing pages in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowCreate: false,
        });

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['allow-new-categories']).toBeUndefined();
    });

    it('should be able to delete landing pages in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['allow-delete-categories']).toBeDefined();
    });

    it('should not be able to delete landing pages in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowDelete: false,
        });

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['allow-delete-categories']).toBeUndefined();
    });

    it('should show the checkbox in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['display-checkbox']).toBeDefined();
    });

    it('should not show the checkbox in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['display-checkbox']).toBeUndefined();
    });

    it('should show the custom tooltip text in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        await wrapper.setProps({
            allowEdit: false,
        });

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBe('sw-privileges.tooltip.warning');
    });

    it('should not show the custom tooltip text in sw-tree-item', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const treeItem = wrapper.find('.sw-tree-item');
        expect(treeItem.attributes()['context-menu-tooltip-text']).toBeUndefined();
    });

    it('should get right landing page url', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const itemUrl = wrapper.vm.getLandingPageUrl({ id: '1a2b' });
        expect(itemUrl).toBe('#/category/landingPage/1a2b');
    });

    it('should get wrong landing page url', async () => {
        const wrapper = await createWrapper();

        await wrapper.setData({
            isLoadingInitialData: false,
        });

        const itemUrl = wrapper.vm.getLandingPageUrl({ id: '1a2b' });
        expect(itemUrl).not.toBe('#/landingPage/1a2b');
    });
});
