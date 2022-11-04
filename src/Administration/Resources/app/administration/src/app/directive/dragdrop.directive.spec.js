import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/directive/dragdrop.directive';

const createWrapper = (dragConf) => {
    const localVue = createLocalVue();

    const div = document.createElement('div');
    div.id = 'root';
    document.body.appendChild(div);

    const dragdropComponent = {
        name: 'dragdrop-component',
        template: `
            <div>
                <span
                    v-for="i in 5"
                    :key="i"
                    :id="getIdName(i)"
                    v-droppable="{ data: { id: i }, dragGroup: 'sw-multi-snippet'}"
                    v-draggable="{ ...dragConf, data: { id: i } }"
                >
                    item {{ i }}
                </span>
            </div>
        `,
        computed: {
            dragConf() {
                return {
                    delay: 200,
                    dragGroup: 'sw-multi-snippet',
                    validDragCls: 'is--valid-drag',
                    onDragStart: this.onDragStart,
                    onDragEnter: this.onDragEnter,
                    onDrop: this.onDrop,
                    ...dragConf
                };
            },
        },
        methods: {
            onDragStart(dragConfig, draggedElement, dragElement) {
                this.$emit('drag-start', { dragConfig, draggedElement, dragElement });
            },

            onDragEnter(dragData, dropData) {
                this.$emit('drag-enter', { dragData, dropData });
            },

            onDrop(dragData, dropData) {
                this.$emit('drop', { dragData, dropData });
            },

            getIdName(index) {
                return `sw-dragdrop--${index}`;
            }
        }
    };

    return shallowMount(dragdropComponent, {
        localVue,
        attachTo: '#root'
    });
};

describe('directives/dragdrop', () => {
    let wrapper;
    let draggable;
    let droppable;

    beforeAll(() => {
        draggable = Shopware.Directive.getByName('draggable');
        droppable = Shopware.Directive.getByName('droppable');
    });

    it('should be exist class name is--droppable', () => {
        wrapper = createWrapper();

        expect(
            wrapper.findAll('span')
                .at(0)
                .find('.is--droppable')
                .exists()
        ).toBeTruthy();
    });

    it('should be exist class name is--draggable', () => {
        wrapper = createWrapper();

        expect(
            wrapper.findAll('span')
                .at(0)
                .find('.is--draggable')
                .exists()
        ).toBeTruthy();
    });

    it('should remove class name `is--draggable` for the draggable directive', () => {
        createWrapper();

        const mockElement = document.getElementById('sw-dragdrop--1');

        const mockBinding = {
            name: 'draggable',
            value: {
                data: {},
            }
        };

        expect(mockElement.className).toEqual('is--droppable is--draggable');

        draggable.unbind(mockElement, mockBinding);

        expect(mockElement.className).toEqual('is--droppable');
    });

    it('should update data for the droppable directive with default config', () => {
        createWrapper();

        const mockElement = document.getElementById('sw-dragdrop--2');

        const mockBinding = {
            name: 'droppable',
            value: {
                data: {},
                disabled: true,
            },
        };

        expect(mockElement.className).toEqual('is--droppable is--draggable');

        draggable.update(mockElement, mockBinding);

        expect(mockElement.className).toEqual('is--droppable');
    });

    it('should update data for the droppable directive with new config', () => {
        createWrapper({
            disabled: true
        });

        const mockElement = document.getElementById('sw-dragdrop--2');

        const mockBinding = {
            name: 'droppable',
            value: {
                data: {},
            },
        };

        expect(mockElement.className).toEqual('is--droppable');

        draggable.update(mockElement, mockBinding);

        expect(mockElement.className).toEqual('is--droppable is--draggable');
    });

    it('should remove class name `is--droppable` for the droppable directive', () => {
        createWrapper();

        const mockElement = document.getElementById('sw-dragdrop--3');

        const mockBinding = {
            name: 'droppable',
            value: {
                data: {},
            },
        };

        expect(mockElement.className).toEqual('is--droppable is--draggable');

        droppable.unbind(mockElement, mockBinding);

        expect(mockElement.className).toEqual('is--draggable');
    });
});
