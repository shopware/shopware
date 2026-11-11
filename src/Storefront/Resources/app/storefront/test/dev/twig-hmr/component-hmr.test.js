import ShopwareComponent from 'src/component-system/component';
import { Shopware } from 'src/component-system/shopware';
import { findComponentName, replace } from 'src/dev/twig-hmr/component-hmr';

const COUNTER_URL = 'http://localhost:5173/@fs/project/Resources/views/components/Sw/Counter/index.js';

class CounterComponent extends ShopwareComponent {
    static destroyed = 0;

    init() {
        this.version = 1;
    }

    destroy() {
        CounterComponent.destroyed += 1;
    }
}

class NewCounterComponent extends ShopwareComponent {
    init() {
        this.version = 2;
    }
}

/**
 * @sw-package framework
 */
describe('dev/twig-hmr/component-hmr', () => {
    beforeEach(() => {
        jest.spyOn(console, 'info').mockImplementation(() => {});
        jest.spyOn(console, 'error').mockImplementation(() => {});
        CounterComponent.destroyed = 0;
        document.head.innerHTML = `<script type="importmap">${JSON.stringify({
            imports: {
                shopware: 'http://localhost:5173/src/shopware.ts',
                'Sw:Counter': COUNTER_URL,
                'Sw:': 'http://localhost:5173/@fs/project/Resources/views/components/Sw/',
            },
        })}</script>`;
        document.body.innerHTML = '<div data-component="Sw:Counter"></div><div data-component="Sw:Counter"></div>';
    });

    afterEach(() => {
        Shopware.disconnect();
        jest.restoreAllMocks();
    });

    function startCounters() {
        return [...document.querySelectorAll('[data-component]')].map((element) => {
            return Shopware.initializeComponentOnElement('Sw:Counter', CounterComponent, element);
        });
    }

    it('finds the component name of an updated module URL in the import map', () => {
        expect(findComponentName(`${COUNTER_URL}?t=1730550000000`)).toBe('Sw:Counter');
        expect(findComponentName('http://localhost:5173/@fs/project/Resources/views/components/Sw/Counter/helper.js')).toBeNull();
    });

    it('skips import maps that are invalid or have no imports', () => {
        document.head.insertAdjacentHTML('afterbegin', '<script type="importmap">{invalid</script><script type="importmap">{}</script>');

        expect(findComponentName(COUNTER_URL)).toBe('Sw:Counter');
    });

    it('asks for a reload if the component system has no replaceComponent', () => {
        const original = window.Shopware;
        window.Shopware = {};

        try {
            expect(replace(COUNTER_URL, NewCounterComponent)).toBe(false);
        } finally {
            window.Shopware = original;
        }
    });

    it('destroys the instances and starts them again with the new class on the same elements', () => {
        const [first, second] = startCounters();

        expect(replace(`${COUNTER_URL}?t=1`, NewCounterComponent)).toBe(true);

        expect(CounterComponent.destroyed).toBe(2);
        const instances = Shopware.getComponentInstances('Sw:Counter');
        expect(instances.map((instance) => instance.version)).toEqual([2, 2]);
        expect(instances.map((instance) => instance.el)).toEqual([first.el, second.el]);
    });

    it('asks for a reload if the module is no component or exports no class', () => {
        startCounters();

        expect(replace('http://localhost:5173/@fs/project/Resources/views/components/Sw/Counter/helper.js', NewCounterComponent)).toBe(false);
        expect(replace(COUNTER_URL, undefined)).toBe(false);
        expect(CounterComponent.destroyed).toBe(0);
    });

    it('asks for a reload if the new class fails to start', () => {
        startCounters();

        class BrokenComponent extends ShopwareComponent {
            init() {
                throw new Error('broken');
            }
        }

        expect(replace(COUNTER_URL, BrokenComponent)).toBe(false);
        expect(console.error).toHaveBeenCalledWith(expect.stringContaining('Sw:Counter could not be replaced'), expect.any(Error));
    });
});
