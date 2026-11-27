/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';
import PrivilegesService from 'src/app/service/privileges.service';

type Mapping = {
    category: 'permissions' | 'additional_permissions';
    parent: string | null;
    key: string;
    roles: Record<string, { privileges: string[]; dependencies: string[] }>;
    containsPersonalData?: boolean;
};

function permission(key: string, roles: Record<string, string[]>, extra: Partial<Mapping> = {}): Mapping {
    return {
        category: 'permissions',
        parent: 'settings',
        key,
        roles: Object.fromEntries(
            Object.entries(roles).map(([role, privileges]) => [
                role,
                { privileges, dependencies: [] },
            ]),
        ),
        ...extra,
    };
}

const baseMappings: Mapping[] = [
    permission('customer', { creator: ['customer:create'] }, { containsPersonalData: true }),
    permission('rule', {
        creator: [
            'rule:create',
            'rule_condition:create',
        ],
    }),
    permission('media', { creator: ['media:create'] }),
    permission('currency', { creator: ['shared_entity:create'] }),
    permission('tax', { creator: ['shared_entity:create'] }),
    permission('product', {
        viewer: [
            'customer:read',
            'media:read',
            'shared_entity:read',
        ],
    }),
    permission('promotion', {
        viewer: ['customer:read'],
        editor: [
            'rule:read',
            'rule:update',
        ],
    }),
    permission(
        'flow',
        {
            viewer: [
                'rule:read',
                'rule:update',
                'rule:create',
                'rule:delete',
                'rule_condition:read',
            ],
        },
        // Only permission rows own entities, so an additional permission can grant all operations on another area
        { category: 'additional_permissions', parent: null },
    ),
    permission('country', { viewer: ['customer:read'] }),
    permission('system', { clear_cache: ['customer:read'] }, { category: 'additional_permissions', parent: null }),
];

const snippets: Record<string, string> = {
    'global.entities.customer': 'customer',
    'global.entities.rule': 'rule',
    'sw-users-permissions.roles.included-access.operations.read': 'view',
    'sw-users-permissions.roles.included-access.operations.update': 'edit',
    'sw-users-permissions.roles.included-access.sharedData': 'Shared data',
};

const translate = (key: string, params: Record<string, unknown> = {}) => {
    if (snippets[key]) {
        return snippets[key];
    }

    return Object.entries(params).reduce((text, [name, value]) => `${text} ${name}=${String(value)}`, key);
};

async function createWrapper(props: Record<string, unknown>, mappings: Mapping[] = baseMappings) {
    const privilegesService = new PrivilegesService();
    mappings.forEach((mapping) => privilegesService.addPrivilegeMappingEntry(mapping));

    const wrapper = mount(await wrapTestComponent('sw-users-permissions-included-access-modal', { sync: true }), {
        props: {
            title: 'Title',
            ...props,
        },
        global: {
            stubs: {
                'mt-modal-root': {
                    props: ['isOpen'],
                    emits: ['change'],
                    template:
                        '<div v-if="isOpen"><slot /><button class="mt-modal-root__dismiss" @click="$emit(\'change\', false)" /></div>',
                },
                'mt-modal': {
                    props: [
                        'title',
                        'subtitle',
                    ],
                    template: '<div><slot /><slot name="footer" /></div>',
                },
                'mt-badge': {
                    props: ['variant'],
                    template: '<span class="mt-badge" :class="`mt-badge--${variant}`"><slot /></span>',
                },
                'mt-modal-close': {
                    template: '<button class="mt-modal-close"><slot /></button>',
                },
            },
            provide: { privileges: privilegesService },
            mocks: {
                $t: translate,
                $tc: (key: string, count: number, params: Record<string, unknown> = {}) => translate(key, params),
                $te: (key: string) => key in snippets,
            },
        },
    });

    await flushPromises();

    return wrapper;
}

const className = 'sw-users-permissions-included-access-modal';
const sections = (wrapper: ReturnType<typeof mount>) => {
    return wrapper.findAll(`.${className}__section`).map((section) => {
        return {
            title: section.find(`.${className}__section-title`).text(),
            count: section.find('.mt-badge').text(),
            variant: section.find('.mt-badge').classes(),
        };
    });
};
const rows = (wrapper: ReturnType<typeof mount>, tier: string) => {
    return wrapper.findAll(`.${className}__section--${tier} .${className}__row:not(.${className}__row--head)`).map((row) => {
        return {
            area: row.find(`.${className}__area`).text(),
            operations: row.findAll(`.${className}__operations`).map((operations) => operations.text()),
            entities: row.findAll(`.${className}__entities`).map((entities) => entities.text()),
            sources: row.find(`.${className}__sources`).exists() ? row.find(`.${className}__sources`).text() : null,
        };
    });
};
const readAreas = (wrapper: ReturnType<typeof mount>) => {
    return wrapper.findAll(`.${className}__read-area`).map((area) => area.text());
};

describe('src/module/sw-users-permissions/components/sw-users-permissions-included-access-modal', () => {
    it('should order the areas by tier: personal data, then changes, then plain reads', async () => {
        const wrapper = await createWrapper({
            privilegeKeys: [
                'product.viewer',
                'promotion.editor',
            ],
        });

        expect(sections(wrapper).map(({ title, count }) => [title, count])).toEqual([
            ['sw-users-permissions.roles.included-access.tiers.personal', '1'],
            ['sw-users-permissions.roles.included-access.tiers.write', '1'],
            ['sw-users-permissions.roles.included-access.tiers.read', '2'],
        ]);
        expect(rows(wrapper, 'personal')).toEqual([
            {
                area: 'sw-privileges.permissions.customer.label',
                operations: ['View'],
                entities: ['Customer'],
                sources: null,
            },
        ]);
        expect(readAreas(wrapper)).toEqual([
            'sw-privileges.permissions.media.label',
            'Shared data',
        ]);
    });

    it('should use the badge variant of each tier', async () => {
        const wrapper = await createWrapper({
            privilegeKeys: [
                'product.viewer',
                'promotion.editor',
            ],
        });

        const variants = sections(wrapper).map(({ variant }) => variant.join(' '));

        expect(variants[0]).toContain('critical');
        expect(variants[1]).toContain('attention');
        expect(variants[2]).toContain('neutral');
    });

    it('should show full access for all four operations and the remaining entities in their own line', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['flow.viewer'] });

        expect(rows(wrapper, 'write')).toEqual([
            {
                area: 'sw-privileges.permissions.rule.label',
                operations: [
                    'sw-users-permissions.roles.included-access.fullAccess',
                    'View',
                ],
                entities: [
                    'Rule',
                    'Rule condition',
                ],
                sources: null,
            },
        ]);
    });

    it('should list shared data last and explain it', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['special.editor'] }, [
            ...baseMappings,
            permission('special', {
                editor: [
                    'shared_entity:read',
                    'shared_entity:update',
                    'media:read',
                    'media:update',
                ],
            }),
        ]);

        const writeRows = wrapper.findAll(`.${className}__section--write .${className}__row:not(.${className}__row--head)`);

        expect(rows(wrapper, 'write').map(({ area }) => area)).toEqual([
            'sw-privileges.permissions.media.label',
            'Shared data',
        ]);
        expect(writeRows[0].find(`.${className}__area-hint`).exists()).toBe(false);
        expect(writeRows[1].find(`.${className}__area-hint`).text()).toBe(
            'sw-users-permissions.roles.included-access.sharedDataDescription',
        );
    });

    it('should list the sources of a read area in its tooltip, one per line', async () => {
        const wrapper = await createWrapper(
            {
                privilegeKeys: [
                    'product.viewer',
                    'special.viewer',
                ],
                withSources: true,
            },
            [
                ...baseMappings,
                permission('special', { viewer: ['media:read'] }),
            ],
        );

        expect(wrapper.find(`.${className}__read-area`).attributes('tooltip-mock-message')).toBe(
            [
                'sw-users-permissions.roles.included-access.sourceEntry area=sw-privileges.permissions.product.label role=sw-privileges.roles.viewer',
                'sw-users-permissions.roles.included-access.sourceEntry area=sw-privileges.permissions.special.label role=sw-privileges.roles.viewer',
            ].join(',<br>'),
        );
    });

    it('should list the entities of a read area in its tooltip without sources', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['product.viewer'] });

        expect(wrapper.find(`.${className}__read-area`).attributes('tooltip-mock-message')).toBe('Media');
    });

    it('should show granted by only with sources', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['promotion.editor'] });

        expect(wrapper.find(`.${className}__sources`).exists()).toBe(false);
    });

    it('should count the sources and list each with its role in the tooltip', async () => {
        const wrapper = await createWrapper({
            privilegeKeys: [
                'product.viewer',
                'promotion.viewer',
                'country.viewer',
                'system.clear_cache',
            ],
            withSources: true,
        });

        const source = (area: string, role: string) => {
            return `sw-users-permissions.roles.included-access.sourceEntry area=sw-privileges.permissions.${area}.label role=sw-privileges.roles.${role}`;
        };
        const clearCache = 'sw-privileges.additional_permissions.system.clear_cache';

        expect(rows(wrapper, 'personal')[0].sources).toBe('sw-users-permissions.roles.included-access.grantedBy count=4');
        expect(wrapper.find(`.${className}__sources`).attributes('tooltip-mock-message')).toBe(
            [
                clearCache,
                source('country', 'viewer'),
                source('product', 'viewer'),
                source('promotion', 'viewer'),
            ].join(',<br>'),
        );
    });

    it('should name the highest role of an area as its source', async () => {
        const wrapper = await createWrapper({
            privilegeKeys: [
                'promotion.viewer',
                'promotion.editor',
            ],
            withSources: true,
        });

        expect(wrapper.find(`.${className}__section--write .${className}__sources`).attributes('tooltip-mock-message')).toBe(
            'sw-users-permissions.roles.included-access.sourceEntry area=sw-privileges.permissions.promotion.label role=sw-privileges.roles.editor',
        );
    });

    it('should leave out what the owning area grants itself in the role view', async () => {
        const mappings = [...baseMappings];
        mappings[0] = permission(
            'customer',
            {
                viewer: ['customer:read'],
                creator: ['customer:create'],
            },
            { containsPersonalData: true },
        );

        const wrapper = await createWrapper(
            {
                privilegeKeys: [
                    'country.viewer',
                    'customer.viewer',
                ],
                withSources: true,
            },
            mappings,
        );

        expect(wrapper.findAll(`.${className}__section`)).toHaveLength(0);
    });

    it('should show the description', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['product.viewer'], description: 'Why it is needed' });

        expect(wrapper.find(`.${className}__description`).text()).toBe('Why it is needed');
    });

    it('should emit close when the modal is dismissed', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['product.viewer'] });

        await wrapper.find('.mt-modal-root__dismiss').trigger('click');

        expect(wrapper.emitted('close')).toHaveLength(1);
    });
});
