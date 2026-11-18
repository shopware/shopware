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
    'sw-users-permissions.roles.included-access.sharedData': 'Shared data',
};

const translate = (key: string, params: Record<string, unknown> = {}) => {
    if (snippets[key]) {
        return snippets[key];
    }

    return Object.entries(params).reduce((text, [name, value]) => `${text} ${name}=${String(value)}`, key);
};

const source = (area: string, role: string) => {
    return `sw-users-permissions.roles.included-access.sourceEntry area=sw-privileges.permissions.${area}.label role=sw-privileges.roles.${role}`;
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
                'mt-modal-close': {
                    template: '<button class="mt-modal-close"><slot /></button>',
                },
                'sw-users-permissions-included-access-area': await wrapTestComponent(
                    'sw-users-permissions-included-access-area',
                    { sync: true },
                ),
                'sw-users-permissions-included-access-mark': await wrapTestComponent(
                    'sw-users-permissions-included-access-mark',
                    { sync: true },
                ),
                'sw-users-permissions-included-access-granted-by': await wrapTestComponent(
                    'sw-users-permissions-included-access-granted-by',
                    { sync: true },
                ),
                MtCollapsible: {
                    template: '<tbody><slot /></tbody>',
                },
                MtCollapsibleTrigger: {
                    template: '<button><slot /></button>',
                },
                MtCollapsibleContent: {
                    template: '<div><slot /></div>',
                },
                'mt-tooltip': {
                    props: ['content'],
                    template: '<span class="mt-tooltip" :data-content="content"><slot /></span>',
                },
                'mt-floating-ui': {
                    props: ['isOpened'],
                    emits: ['close'],
                    template: `
                        <div class="mt-floating-ui">
                            <slot name="trigger" />
                            <div v-if="isOpened" class="mt-floating-ui__content"><slot /></div>
                            <button class="mt-floating-ui__outside" @click="$emit('close')" />
                        </div>
                    `,
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
const areaClass = 'sw-users-permissions-included-access-area';
const markClass = 'sw-users-permissions-included-access-mark';

type Wrapper = Awaited<ReturnType<typeof createWrapper>>;

const groups = (wrapper: Wrapper) => {
    return wrapper.findAll(`.${className}__group th`).map((heading) => heading.text());
};

const areas = (wrapper: Wrapper) => {
    return wrapper.findAll(`.${areaClass}__label`).map((label) => label.text());
};

const area = (wrapper: Wrapper, label: string) => {
    const match = wrapper.findAll(`.${areaClass}`).find((candidate) => {
        return candidate.find(`.${areaClass}__label`).text() === label;
    });

    if (!match) {
        throw new Error(`No area ${label}`);
    }

    return match;
};

const marks = (wrapper: Wrapper, label: string) => {
    return area(wrapper, label)
        .findAll(`.${areaClass}__cell`)
        .map((cell) => {
            if (cell.find(`.${markClass}--partial`).exists()) {
                return 'some';
            }

            return cell.find(`.${markClass}`).exists() ? 'all' : 'none';
        });
};

const entityRows = (wrapper: Wrapper, label: string) => {
    return area(wrapper, label)
        .findAll(`.${areaClass}__entity`)
        .map((row) => {
            return {
                label: row.find(`.${areaClass}__entity-label`).text(),
                cells: row.findAll(`.${areaClass}__entity-cell`).map((cell) => {
                    const tooltip = cell.find('.mt-tooltip');

                    return tooltip.exists() ? (tooltip.attributes('data-content') ?? null) : null;
                }),
            };
        });
};

describe('src/module/sw-users-permissions/components/sw-users-permissions-included-access-modal', () => {
    it('should group the areas by tier: personal data, then changes, then plain reads', async () => {
        const wrapper = await createWrapper({
            privilegeKeys: [
                'product.viewer',
                'promotion.editor',
            ],
        });

        expect(groups(wrapper)).toEqual([
            'sw-users-permissions.roles.included-access.tiers.personal',
            'sw-users-permissions.roles.included-access.tiers.write',
            'sw-users-permissions.roles.included-access.tiers.read',
        ]);
        expect(areas(wrapper)).toEqual([
            'sw-privileges.permissions.customer.label',
            'sw-privileges.permissions.rule.label',
            'sw-privileges.permissions.media.label',
            'Shared data',
        ]);
    });

    it('should name the roles of the permissions grid as columns', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['product.viewer'] });

        expect(wrapper.findAll(`.${className}__operation-head`).map((head) => head.text())).toEqual([
            'sw-privileges.roles.viewer',
            'sw-privileges.roles.editor',
            'sw-privileges.roles.creator',
            'sw-privileges.roles.deleter',
        ]);
    });

    it('should mark an operation as partial when only some data of the area gets it', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['flow.viewer'] });
        const label = 'sw-privileges.permissions.rule.label';

        expect(marks(wrapper, label)).toEqual([
            'all',
            'some',
            'some',
            'some',
        ]);

        const partial = area(wrapper, label).find(`.${markClass}--partial`);
        expect(partial.text()).toBe('sw-users-permissions.roles.included-access.partialAccessShort count=1 total=2');
        expect(area(wrapper, label).findAll(`.${areaClass}__cell .mt-tooltip`)[1].attributes('data-content')).toBe(
            [
                'sw-users-permissions.roles.included-access.partialAccess count=1 total=2 entities=Rule',
                'sw-privileges.additional_permissions.flow.viewer',
            ].join('<br>'),
        );
    });

    it('should leave operations without access empty', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['promotion.editor'] });

        expect(marks(wrapper, 'sw-privileges.permissions.rule.label')).toEqual([
            'all',
            'all',
            'none',
            'none',
        ]);
    });

    it('should list the data of an area with the permissions that grant each operation', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['flow.viewer'] });
        const flow = 'sw-privileges.additional_permissions.flow.viewer';

        expect(entityRows(wrapper, 'sw-privileges.permissions.rule.label')).toEqual([
            {
                label: 'Rule',
                cells: [
                    flow,
                    flow,
                    flow,
                    flow,
                ],
            },
            {
                label: 'Rule condition',
                cells: [
                    flow,
                    null,
                    null,
                    null,
                ],
            },
        ]);
    });

    it('should name the lowest selected role that grants an operation as its source', async () => {
        const wrapper = await createWrapper(
            {
                privilegeKeys: [
                    'special.viewer',
                    'special.editor',
                ],
                withSources: true,
            },
            [
                ...baseMappings,
                permission('special', {
                    viewer: ['media:read'],
                    editor: [
                        'media:read',
                        'media:update',
                    ],
                }),
            ],
        );

        expect(entityRows(wrapper, 'sw-privileges.permissions.media.label')).toEqual([
            {
                label: 'Media',
                cells: [
                    source('special', 'viewer'),
                    source('special', 'editor'),
                    null,
                    null,
                ],
            },
        ]);
    });

    it('should list each granting area once with its roles in the granted by popover', async () => {
        const wrapper = await createWrapper({
            privilegeKeys: [
                'product.viewer',
                'promotion.viewer',
                'country.viewer',
                'system.clear_cache',
            ],
            withSources: true,
        });
        const customer = area(wrapper, 'sw-privileges.permissions.customer.label');

        expect(customer.find('.sw-users-permissions-included-access-granted-by__trigger').text()).toBe(
            'sw-users-permissions.roles.included-access.grantedBy count=4',
        );
        expect(customer.find('.sw-users-permissions-included-access-granted-by__description').text()).toBe(
            [
                'sw-privileges.additional_permissions.system.clear_cache',
                'sw-privileges.permissions.country.label: sw-privileges.roles.viewer',
                'sw-privileges.permissions.product.label: sw-privileges.roles.viewer',
                'sw-privileges.permissions.promotion.label: sw-privileges.roles.viewer',
            ].join('; '),
        );
    });

    it('should list the granting permissions only in the role view', async () => {
        const trigger = '.sw-users-permissions-included-access-granted-by__trigger';
        const rowView = await createWrapper({ privilegeKeys: ['promotion.editor'] });
        const roleView = await createWrapper({ privilegeKeys: ['promotion.editor'], withSources: true });

        expect(rowView.find(trigger).exists()).toBe(false);
        expect(roleView.find(trigger).exists()).toBe(true);
    });

    it('should explain shared data in its details', async () => {
        const wrapper = await createWrapper({ privilegeKeys: ['product.viewer'] });

        expect(area(wrapper, 'Shared data').find(`.${areaClass}__shared-hint`).text()).toBe(
            'sw-users-permissions.roles.included-access.sharedDataDescription',
        );
        expect(area(wrapper, 'sw-privileges.permissions.media.label').find(`.${areaClass}__shared-hint`).exists()).toBe(
            false,
        );
    });

    it('should list shared data last in its group', async () => {
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

        expect(areas(wrapper)).toEqual([
            'sw-privileges.permissions.media.label',
            'Shared data',
        ]);
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

        expect(groups(wrapper)).toHaveLength(0);
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
