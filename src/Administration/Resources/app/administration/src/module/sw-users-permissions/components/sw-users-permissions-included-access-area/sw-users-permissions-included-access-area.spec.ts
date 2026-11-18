/**
 * @sw-package fundamentals@framework
 */
import { mount } from '@vue/test-utils';
import type { IncludedAccessArea, IncludedAccessColumn } from '../sw-users-permissions-included-access-modal';

const className = 'sw-users-permissions-included-access-area';

const columns: IncludedAccessColumn[] = [
    { operation: 'read', role: 'viewer' },
    { operation: 'update', role: 'editor' },
    { operation: 'create', role: 'creator' },
    { operation: 'delete', role: 'deleter' },
];

function createArea(overrides: Partial<IncludedAccessArea> = {}): IncludedAccessArea {
    return {
        key: 'media',
        label: 'Media',
        tier: 'write',
        isShared: false,
        entityCount: 2,
        coverage: {
            read: { level: 'all', entities: ['Media', 'Folder <b>'], sources: ['Products › View'] },
            update: { level: 'some', entities: ['Media'], sources: ['Products › Edit'] },
            create: { level: 'none', entities: [], sources: [] },
            delete: { level: 'none', entities: [], sources: [] },
        },
        entityRows: [
            {
                key: 'media',
                label: 'Media',
                cells: { read: ['Products › View'], update: ['Products › Edit'], create: null, delete: null },
            },
            {
                key: 'media_folder',
                label: 'Folder <b>',
                cells: { read: ['Products › View'], update: null, create: null, delete: null },
            },
        ],
        grantedBy: [{ key: 'product', label: 'Products', roles: 'View, Edit' }],
        ...overrides,
    };
}

async function createWrapper(area: IncludedAccessArea = createArea(), showGrantedBy = true) {
    return mount(await wrapTestComponent('sw-users-permissions-included-access-area', { sync: true }), {
        props: { area, columns, showGrantedBy },
        global: {
            stubs: {
                MtCollapsible: { template: '<tbody><slot /></tbody>' },
                MtCollapsibleTrigger: { template: '<button><slot /></button>' },
                MtCollapsibleContent: { template: '<div><slot /></div>' },
                'sw-users-permissions-included-access-mark': {
                    props: [
                        'variant',
                        'tooltip',
                        'label',
                    ],
                    template: '<span class="mark-stub" :data-variant="variant" :data-tooltip="tooltip">{{ label }}</span>',
                },
                'sw-users-permissions-included-access-granted-by': {
                    props: [
                        'popoverId',
                        'grantedBy',
                    ],
                    template: '<div class="granted-by-stub" :data-id="popoverId">{{ grantedBy.length }}</div>',
                },
            },
            mocks: {
                $t: (key: string, params: Record<string, unknown> = {}) => {
                    return Object.entries(params).reduce((text, [name, value]) => `${text} ${name}=${String(value)}`, key);
                },
            },
        },
    });
}

describe('src/module/sw-users-permissions/components/sw-users-permissions-included-access-area', () => {
    it('should mark each operation of the area row by its coverage', async () => {
        const wrapper = await createWrapper();
        const cells = wrapper.findAll(`.${className}__row .${className}__cell`);

        const variants = cells.map((cell) => {
            const mark = cell.find('.mark-stub');

            return mark.exists() ? mark.attributes('data-variant') : 'none';
        });

        expect(variants).toEqual([
            'full',
            'partial',
            'none',
            'none',
        ]);
        expect(cells[2].text()).toBe('sw-users-permissions.roles.included-access.noAccess');
    });

    it('should name the permissions in the tooltip and escape the labels', async () => {
        const wrapper = await createWrapper();
        const marks = wrapper.findAll(`.${className}__row .mark-stub`);

        expect(marks[0].attributes('data-tooltip')).toBe('Products › View');
        expect(marks[1].attributes('data-tooltip')).toBe(
            'sw-users-permissions.roles.included-access.partialAccess count=1 total=2 entities=Media<br>Products › Edit',
        );
        expect(marks[1].text()).toBe('sw-users-permissions.roles.included-access.partialAccessShort count=1 total=2');
    });

    it('should list the data of the area with muted marks', async () => {
        const wrapper = await createWrapper();
        const rows = wrapper.findAll(`.${className}__entity`);

        expect(rows.map((row) => row.find(`.${className}__entity-label`).text())).toEqual([
            'Media',
            'Folder <b>',
        ]);
        expect(rows[1].findAll('.mark-stub').map((mark) => mark.attributes('data-variant'))).toEqual(['muted']);
        expect(rows[1].find('.mark-stub').text()).toBe('sw-privileges.roles.viewer');
    });

    it('should hand the granting permissions to the popover', async () => {
        const wrapper = await createWrapper();

        expect(wrapper.find('.granted-by-stub').attributes('data-id')).toBe('media');
        expect(wrapper.find('.granted-by-stub').text()).toBe('1');
    });

    it('should leave out the granting permissions when asked to', async () => {
        const wrapper = await createWrapper(createArea(), false);

        expect(wrapper.find('.granted-by-stub').exists()).toBe(false);
    });

    it('should explain shared data only for the shared area', async () => {
        expect((await createWrapper()).find(`.${className}__shared-hint`).exists()).toBe(false);
        expect(
            (await createWrapper(createArea({ key: 'shared', isShared: true }))).find(`.${className}__shared-hint`).text(),
        ).toBe('sw-users-permissions.roles.included-access.sharedDataDescription');
    });
});
