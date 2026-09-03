/**
 * @sw-package fundamentals@after-sales
 */

import './index';
import { searchRankingPoint } from '../../app/service/search-ranking.service';

jest.mock('./acl', () => jest.fn());

const { Module, Component } = Shopware;

describe('src/module/sw-settings-rule/index.js', () => {
    it('should register & extend components', () => {
        const components = [
            'sw-settings-rule-add-assignment-modal',
            'sw-settings-rule-add-assignment-listing',
            'sw-settings-rule-category-tree',
            'sw-settings-rule-list',
            'sw-settings-rule-detail',
            'sw-settings-rule-detail-base',
            'sw-settings-rule-detail-assignments',
            'sw-settings-rule-assignment-listing',
            'sw-settings-rule-tree-item',
            'sw-settings-rule-tree',
        ];

        const register = Component.getComponentRegistry();

        expect(register.size).toBe(components.length);
        components.forEach((component) => {
            expect(register.get(component)).toBeDefined();
        });
    });

    it('should register module base information', () => {
        const module = Module.getModuleRegistry().get('sw-settings-rule');
        expect(module).toBeDefined();

        expect(module.manifest).toEqual({
            type: 'core',
            name: 'settings-rule',
            title: 'sw-settings-rule.general.mainMenuItemGeneral',
            description: 'sw-settings-rule.general.descriptionTextModule',
            color: '#FF7F74',
            defaultSearchConfiguration: {
                _searchable: true,
                name: {
                    _searchable: true,
                    _score: searchRankingPoint.HIGH_SEARCH_RANKING,
                },
                description: {
                    _searchable: true,
                    _score: searchRankingPoint.LOW_SEARCH_RANKING,
                },
            },
            icon: 'regular-rule',
            favicon: 'icon-module-settings.svg',
            entity: 'rule',
            routes: expect.any(Object),
            navigation: [
                {
                    id: 'sw-settings-rule',
                    label: 'sw-settings-rule.general.mainMenuItemGeneral',
                    path: 'sw.settings.rule.index',
                    icon: 'regular-rule',
                    color: '#FF7F74',
                    parent: 'sw-automation',
                    privilege: 'rule.viewer',
                    moduleType: 'core',
                    position: 10,
                },
                {
                    id: 'sw-automation',
                    label: 'global.sw-admin-menu.navigation.mainMenuItemAutomation',
                    icon: 'regular-rule',
                    color: '#FF7F74',
                    moduleType: 'core',
                    position: 70,
                },
            ],
            settingsItem: [
                {
                    id: 'sw-settings-rule',
                    label: 'sw-settings-rule.general.mainMenuItemGeneral',
                    name: 'settings-rule',
                    group: 'automation',
                    to: 'sw.settings.rule.index',
                    icon: 'regular-rule',
                    privilege: 'rule.viewer',
                },
            ],
            display: true,
        });

        const settingsItem = module.manifest.settingsItem[0];
        expect(typeof settingsItem.group).toBe('string');
        expect(settingsItem.group).toBe('automation');
    });

    it('should register module routes', () => {
        const routes = {
            'sw.settings.rule.index': {
                path: '/sw/settings/rule/index',
                components: { default: 'sw-settings-rule-list' },
            },
            'sw.settings.rule.detail.base': {
                component: 'sw-settings-rule-detail-base',
                path: '/sw/settings/rule/detail/:id/base',
            },
            'sw.settings.rule.detail.assignments': {
                component: 'sw-settings-rule-detail-assignments',
                path: '/sw/settings/rule/detail/:id/assignments',
            },
            'sw.settings.rule.detail': {
                path: '/sw/settings/rule/detail/:id',
                components: { default: 'sw-settings-rule-detail' },
            },
            'sw.settings.rule.create.base': {
                component: 'sw-settings-rule-detail-base',
                path: '/sw/settings/rule/create/base',
            },
            'sw.settings.rule.create': {
                path: '/sw/settings/rule/create',
                components: { default: 'sw-settings-rule-detail' },
            },
        };

        const register = Module.getModuleRegistry().get('sw-settings-rule').routes;
        expect(register).toBeDefined();

        expect(register.size).toBe(Object.keys(routes).length);
        Object.keys(routes).forEach((name) => {
            const route = register.get(name);

            expect(route.path).toBe(routes[name].path);
            expect(route.component).toBe(routes[name].component);
        });
    });
});
