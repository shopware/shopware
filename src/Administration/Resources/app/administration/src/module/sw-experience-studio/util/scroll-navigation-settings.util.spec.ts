import type { ContentElementNode } from 'src/core/service/content-element.types';
import type { ContentLayoutEntity } from './content-layout-repository.util';
import {
    pruneScrollNavigationAnchors,
    readScrollNavigationSettings,
    scrollNavigationSettingsPayload,
} from './scroll-navigation-settings.util';

describe('module/sw-experience-studio/util/scroll-navigation-settings.util', () => {
    function layoutWith(settings: unknown): ContentLayoutEntity {
        return { settings } as unknown as ContentLayoutEntity;
    }

    it('returns the inactive sidebar default for a missing or malformed member', () => {
        const expected = { active: false, mode: 'sidebar', position: 'right', anchors: {} };

        expect(readScrollNavigationSettings(null)).toEqual(expected);
        expect(readScrollNavigationSettings(layoutWith(null))).toEqual(expected);
        expect(readScrollNavigationSettings(layoutWith({ scrollNavigation: 'yes' }))).toEqual(expected);
        expect(readScrollNavigationSettings(layoutWith({ scrollNavigation: ['a'] }))).toEqual(expected);
    });

    it('normalises every member of a stored object', () => {
        const settings = readScrollNavigationSettings(
            layoutWith({
                scrollNavigation: {
                    active: 'true',
                    mode: 'tree',
                    position: 'top',
                    anchors: {
                        'element-a': { label: 'Intro' },
                        'element-b': { label: 7 },
                        'element-c': 'broken',
                    },
                },
            }),
        );

        expect(settings).toEqual({
            active: false,
            mode: 'sidebar',
            position: 'right',
            anchors: {
                'element-a': { label: 'Intro' },
                'element-b': { label: '' },
            },
        });
    });

    it('keeps a stored flat presentation', () => {
        const settings = readScrollNavigationSettings(
            layoutWith({ scrollNavigation: { active: true, mode: 'flat', position: 'left' } }),
        );

        expect(settings).toEqual({ active: true, mode: 'flat', position: 'left', anchors: {} });
    });

    it('prunes anchors whose element left the tree and keeps nested ones', () => {
        const tree = [
            {
                id: 'root',
                component: 'Sw:Grid:Container',
                slots: { content: [{ id: 'nested', component: 'Sw:Content:Text' }] },
            },
        ] as unknown as ContentElementNode[];
        const settings = {
            active: true,
            mode: 'sidebar' as const,
            position: 'right' as const,
            anchors: { root: { label: 'Root' }, nested: { label: 'Nested' }, gone: { label: 'Gone' } },
        };

        expect(pruneScrollNavigationAnchors(settings, tree)).toEqual({
            ...settings,
            anchors: { root: { label: 'Root' }, nested: { label: 'Nested' } },
        });
    });

    it('returns the same settings object when nothing is stale', () => {
        const tree = [{ id: 'root', component: 'Sw:Grid:Container' }] as unknown as ContentElementNode[];
        const settings = { active: true, mode: 'flat' as const, position: 'left' as const, anchors: { root: { label: '' } } };

        expect(pruneScrollNavigationAnchors(settings, tree)).toBe(settings);
    });

    it('wraps the settings into the update payload under the scrollNavigation key', () => {
        const settings = { active: true, mode: 'flat' as const, position: 'left' as const, anchors: {} };

        expect(scrollNavigationSettingsPayload(settings)).toEqual({
            settings: { scrollNavigation: settings },
        });
    });
});
