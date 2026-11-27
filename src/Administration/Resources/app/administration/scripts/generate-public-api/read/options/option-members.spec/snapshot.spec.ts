/**
 * @sw-package framework
 *
 * How the snapshot shows the members of a component. `runtime.spec.ts` checks the names against the options
 * objects of the real Administration, these tests the single forms a member can be written in.
 */
import { MAJOR } from '../../../testing/example-components';
import { cardUnit, reportOf, section } from './sources';

describe('scripts/generate-public-api/read/options/option-members in the snapshot', () => {
    describe('props', () => {
        it('prints the type, whether the prop is required and its default value or factory', () => {
            const options = `props: {
                title: { type: String, required: true },
                size: { type: [String, Number], default: 'm' },
                items: { type: Array, default: () => [] },
                entity: { type: Object, default() { return null; } },
                onSave: Function,
                anything: { default: null },
            }`;

            expect(section('props', { options })).toEqual([
                'anything?: any  default=null',
                'entity?: object  default=factory',
                'items?: any[]  default=factory',
                'onSave?: (...args: any[]) => any',
                "size?: number | string  default='m'",
                'title: string',
            ]);
        });

        it('prints the type a TypeScript component declares with PropType', () => {
            const script = `
                export default Shopware.Component.wrapComponentConfig({
                    props: { product: { type: Object as PropType<Entity<'product'>>, required: true } },
                });
            `;

            expect(section('props', { script, extension: 'ts' })).toEqual(["product: Entity<'product'>"]);
        });

        it('lists the props of the array form without a type', () => {
            expect(section('props', { options: "props: ['title', 'size']" })).toEqual(['size?: any', 'title?: any']);
        });

        it('reads props that a const of the same file holds', () => {
            const card = { preamble: 'const props = { title: String };', options: 'props' };

            expect(section('props', card)).toEqual(['title?: string']);
        });
    });

    describe('emits', () => {
        it('prints the array form with any payload and the object form with the parameters of its validator', () => {
            expect(section('emits', { options: "emits: ['close', 'update:value']" })).toEqual([
                'close(...args: any[])',
                'update:value(...args: any[])',
            ]);
            expect(
                section('emits', {
                    options: 'emits: { save: (value: number, silent?: boolean) => true, close: null }',
                    extension: 'ts',
                }),
            ).toEqual(['close(...args: any[])', 'save(value: number, silent?: boolean)']);
        });
    });

    describe('mixins, inject and provide', () => {
        it('prints the registered mixins on one line in the order of the source', () => {
            const options = `mixins: [
                Mixin.getByName('notification'),
                Shopware.Mixin.getByName('discard-detail-page-changes')('product'),
                'listing',
            ]`;

            expect(section('mixins', { options })).toEqual(['notification, discard-detail-page-changes, listing']);
        });

        it('notes a mixin whose name the source does not write as a literal', () => {
            const card = { options: 'mixins: [Mixin.getByName(MIXIN_NAME), localMixin]' };

            expect(section('mixins', card)).toEqual([]);
            expect(section('notes', card)).toEqual([
                'unresolved mixins Mixin.getByName(MIXIN_NAME)',
                'unresolved mixins localMixin',
            ]);
        });

        it('prints the key an injection has under another local name', () => {
            expect(section('inject', { options: "inject: ['acl', 'repositoryFactory']" })).toEqual([
                'acl',
                'repositoryFactory',
            ]);
            expect(
                section('inject', {
                    options:
                        "inject: { factory: 'repositoryFactory', feature: { from: 'feature', default: null }, acl: {} }",
                }),
            ).toEqual(['acl', 'factory from repositoryFactory', 'feature']);
        });

        it('lists what provide() returns', () => {
            expect(
                section('provide', { options: 'provide() { return { swCardSave: this.onSave, swCardId: 1 }; }' }),
            ).toEqual([
                'swCardId: any',
                'swCardSave: any',
            ]);
        });
    });

    describe('data', () => {
        it('prints the keys of a nested object below their parent', () => {
            const options = `data() {
                return { isLoading: false, pagination: { page: 1, limit: 25 }, items: [], empty: {} };
            }`;

            expect(section('data', { options })).toEqual([
                'empty: any',
                'isLoading: any',
                'items: any',
                'pagination: object',
                '  limit: any',
                '  page: any',
            ]);
        });

        it('notes what a spread adds and keeps the keys beside it', () => {
            const card = { options: 'data() { return { isLoading: false, ...defaults }; }' };

            expect(section('data', card)).toEqual(['isLoading: any']);
            expect(section('notes', card)).toEqual(['unresolved data ...defaults']);
        });

        it('notes a data function that returns no object literal, with the start of its source', () => {
            const card = {
                options: `data() { const state = { isLoading: false, ${'x'.repeat(80)}: 1 }; return state; }`,
            };

            expect(section('data', card)).toEqual([]);
            expect(section('notes', card)).toEqual([
                `unresolved data data() { const state = { isLoading: false, ${'x'.repeat(37)}...`,
            ]);
        });
    });

    describe('computed properties', () => {
        it('lists getters, getter and setter pairs and arrow functions', () => {
            const options = `computed: {
                hasTitle() { return true; },
                value: { get() { return 1; }, set(value) {} },
                total: (vm) => vm.items.length,
            }`;

            expect(section('computed', { options })).toEqual(['hasTitle: any', 'total: any', 'value: any']);
        });

        it('prints the return type a TypeScript getter declares', () => {
            const options = 'computed: { total(): number { return 1; }, value: { get(): string { return ""; } } }';

            expect(section('computed', { options, extension: 'ts' })).toEqual(['total: number', 'value: string']);
        });

        it('adds the names of the helpers that list them as literals', () => {
            const options = `computed: {
                ...mapPropertyErrors('product', ['name', 'taxId']),
                ...mapCollectionPropertyErrors('prices', ['price']),
                ...mapState(useCardStore, ['isOpen']),
                ...mapGetters('cart', { total: 'cartTotal' }),
            }`;

            expect(section('computed', { options })).toEqual([
                'isOpen: any',
                'pricesPriceError: any',
                'productNameError: any',
                'productTaxIdError: any',
                'total: any',
            ]);
            expect(section('notes', { options })).toEqual([]);
        });

        it('notes a spread whose names the source does not list', () => {
            const card = { options: "computed: { ...mapPageErrors(errorConfig), ...mapPropertyErrors('product', fields) }" };

            expect(section('computed', card)).toEqual([]);
            expect(section('notes', card)).toEqual([
                'unresolved computed ...mapPageErrors(errorConfig)',
                "unresolved computed ...mapPropertyErrors('product', fields)",
            ]);
        });
    });

    describe('methods', () => {
        it('prints optional, rest and destructured parameters and an async method', () => {
            const options = `methods: {
                onSave() {},
                onPageChange(page, limit = 25) {},
                onSelect({ id }, ...rest) {},
                async load(criteria) {},
                reset: function (hard) {},
                close: () => {},
            }`;

            expect(section('methods', { options })).toEqual([
                'close(): any',
                'load(criteria: any): Promise<any>',
                'onPageChange(page: any, limit?: any = 25): any',
                'onSave(): any',
                'onSelect(arg0: any, ...rest: any[]): any',
                'reset(hard: any): any',
            ]);
        });

        it('prints a Promise for a method that returns a promise it visibly builds in every return', () => {
            const options = `methods: {
                chained() { return this.repository.save(this.entity).then(() => this.load()); },
                combined() { return Promise.all([this.load(), this.reload()]); },
                created() { return new Promise((resolve) => resolve()); },
                plainCall() { return this.repository.save(this.entity); },
                sometimes() { if (this.isLoading) { return; } return this.load().then(() => {}); },
            }`;

            expect(section('methods', { options })).toEqual([
                'chained(): Promise<any>',
                'combined(): Promise<any>',
                'created(): Promise<any>',
                'plainCall(): any',
                'sometimes(): any',
            ]);
        });

        it('prints the types a TypeScript method declares, without its this parameter', () => {
            const options = 'methods: { load(this: Card, id: string, force?: boolean): Promise<void> {} }';

            expect(section('methods', { options, extension: 'ts' })).toEqual([
                'load(id: string, force?: boolean): Promise<void>',
            ]);
        });

        it('reads the parameters of a method that a helper such as debounce wraps', () => {
            const options =
                'methods: { onSearch: debounce(function (term) {}, 400), onResize: utils.throttle((size) => {}, 50) }';

            expect(section('methods', { options })).toEqual(['onResize(size: any): any', 'onSearch(term: any): any']);
        });

        it('prints a method that is no function in the source as taking anything', () => {
            expect(
                section('methods', { options: 'methods: { format: formatDate, ...mapActions(useStore, ["open"]) }' }),
            ).toEqual([
                'format(...args: any[]): any',
                'open(...args: any[]): any',
            ]);
        });

        it('does not change when only the code inside a method changes', () => {
            const before = 'methods: { load() { return this.items.slice(0, 25); } }';
            const after = 'methods: { load() { return this.items.slice(0, 50).filter(Boolean); } }';

            expect(section('methods', { options: after })).toEqual(section('methods', { options: before }));
        });
    });

    describe('the options object', () => {
        it.each([
            [
                'Shopware.Component.wrapComponentConfig',
                'Shopware.Component.wrapComponentConfig({ props: { title: String } })',
            ],
            ['defineComponent', 'defineComponent({ props: { title: String } })'],
            ['a const', 'config'],
            ['a type assertion', '({ props: { title: String } } as ComponentConfig)'],
        ])('is found behind %s', (_, exported) => {
            const script = `const config = { props: { title: String } };\nexport default ${exported};`;

            expect(section('props', { script, extension: 'ts' })).toEqual(['title?: string']);
        });

        it('notes a source that exports no object literal, a spread into the options and a setup function', () => {
            expect(section('notes', { script: 'export default createCard();' })).toEqual([
                'unresolved options src/module/sw-example/component/sw-example-card/index.js',
            ]);
            expect(section('notes', { options: '...base, setup() { return {}; }' })).toEqual([
                'unresolved options ...base',
                'unresolved options setup',
            ]);
        });

        it('ignores watchers, lifecycle hooks and other options that add no members', () => {
            const unit = cardUnit({ options: 'name: "card", watch: { title() {} }, created() {}, components: {}' });

            expect(unit.members).toEqual([]);
            expect(unit.notes).toEqual([]);
        });
    });

    describe('markers of a member', () => {
        it('shows the markers of a JSDoc comment and of a line comment', () => {
            const options = `methods: {
                /** @deprecated tag:v6.8.0 - Use load() instead. */
                fetch() {},
                // @deprecated tag:v6.8.0 - Will be removed.
                reload() {},
                /**
                 * @bc-change ParameterRemoval version:v6.8.0 parameter:force
                 *   The card always reloads.
                 */
                load(id, force) {},
                /** @private */
                reset() {},
                save() {},
            }`;

            expect(section('methods', { options })).toEqual([
                'fetch(): any  [deprecated tag:v6.8.0]',
                'load(id: any, force: any): any  [bc-change ParameterRemoval v6.8.0 force]',
                'reload(): any  [deprecated tag:v6.8.0]',
                'reset(): any  [private]',
                'save(): any',
            ]);
        });

        it('shows the marker of a block behind its context', () => {
            const template = `
                {% block sw_example_card %}
                <div v-if="isLoading"></div>
                {# @deprecated tag:v6.8.0 - Will be removed. #}
                {% block sw_example_card_title %}{% endblock %}
                {% endblock %}
            `;

            expect(section('blocks', { template })).toEqual([
                'sw_example_card',
                '  sw_example_card_title  [after v-if] [deprecated tag:v6.8.0]',
            ]);
        });
    });

    describe('members of the template', () => {
        it('lists no CSS classes, which are no public API', () => {
            const template = `
                {% block sw_example_card %}
                <div class="sw-example-card is--open" :class="{ 'is--loading': isLoading }"></div>
                {% endblock %}
            `;

            expect(reportOf({ template })).not.toContain('is--open');
            expect(section('classes', { template })).toEqual([]);
        });

        it('lists a ref with the tag it sits on, as a list inside a v-for', () => {
            const template = `
                {% block sw_example_card %}
                <sw-data-grid ref="grid"></sw-data-grid>
                <ul><li v-for="item in items"><input ref="inputs"></li></ul>
                <div :ref="refName"></div>
                {% endblock %}
            `;

            expect(section('refs', { template })).toEqual([
                'grid  -> sw-data-grid  block=sw_example_card',
                'inputs  -> input  list  block=sw_example_card',
            ]);
            expect(section('notes', { template })).toEqual(['dynamic-ref refName']);
        });

        it('lists a slot with the props it passes and the block around it', () => {
            const template = `
                {% block sw_example_card %}
                <slot></slot>
                {% block sw_example_card_footer %}
                <slot name="footer" :item="item" v-bind:index="index" v-bind="extra"></slot>
                {% endblock %}
                <slot :name="slotName"></slot>
                {% endblock %}
            `;

            expect(section('slots', { template })).toEqual([
                'default  {}  block=sw_example_card',
                'footer  { ...extra, index, item }  block=sw_example_card_footer',
            ]);
            expect(section('notes', { template })).toEqual(['dynamic-slot slotName']);
        });

        it('marks a member that only one flag state renders', () => {
            const template = `
                {% block sw_example_card %}
                <sw-tabs v-if="!${MAJOR}" ref="tabs"></sw-tabs>
                <mt-tabs v-else ref="tabs"></mt-tabs>
                {% block sw_example_card_new %}<slot v-if="${MAJOR}" name="icon"></slot>{% endblock %}
                {% endblock %}
            `;

            expect(section('refs', { template })).toEqual([
                'tabs  -> mt-tabs  block=sw_example_card  [from v6.8.0]',
                'tabs  -> sw-tabs  block=sw_example_card  [until v6.8.0]',
            ]);
            expect(section('slots', { template })).toEqual(['icon  {}  block=sw_example_card_new  [from v6.8.0]']);
        });
    });

    it('prints the sections of the members around the blocks in a fixed order', () => {
        const report = reportOf({
            options: `
                mixins: ['listing'], inject: ['acl'], provide() { return { card: this }; },
                props: ['title'], emits: ['close'], data() { return { isOpen: true }; },
                computed: { hasTitle() {} }, methods: { onClose() {} },
            `,
            template: '{% block sw_example_card %}<div ref="root"><slot></slot></div>{% endblock %}',
        });

        expect(report.split('\n').filter((line) => /^[a-z]+$/.test(line))).toEqual([
            'mixins',
            'inject',
            'provide',
            'props',
            'emits',
            'slots',
            'data',
            'computed',
            'methods',
            'blocks',
            'refs',
        ]);
    });
});
