import { Mixin } from 'src/core/shopware';
import { debug } from 'src/core/service/util.service';

Mixin.register('drag-selector', {
    data() {
        return {
            mouseDown: false,
            startPoint: null,
            endPoint: null,
            dragSelection: []
        };
    },

    computed: {
        dragSelectorClass() {
            debug.warn('Drag-Selector Mixin',
                'You have to specify the CSS-Selector class of the selectable Items ' +
                'by defining the computed property "dragSelectorClass".');

            return null;
        },

        _dragIsActive() {
            return !this.mouseDown || !this.startPoint || !this.endPoint;
        },

        _selectionBox() {
            if (this._dragIsActive) {
                return null;
            }

            const rect = this.$el.parentNode.getBoundingClientRect();
            const left = Math.min(this.startPoint.x, this.endPoint.x) - rect.left;
            const top = Math.min(this.startPoint.y, this.endPoint.y) - rect.top;
            const width = Math.abs(this.startPoint.x - this.endPoint.x);
            const height = Math.abs(this.startPoint.y - this.endPoint.y);

            return {
                left,
                top,
                width,
                height
            };
        },

        _selectionBoxStyling() {
            if (this._dragIsActive) {
                return null;
            }

            return `
                left: ${this._selectionBox.left}px;
                top: ${this._selectionBox.top}px;
                width: ${this._selectionBox.width}px;
                height: ${this._selectionBox.height}px;
            `;
        }
    },

    methods: {
        onDragSelection() {
            debug.warn('Drag-Selector Mixin',
                'You have to override the "onDragSelection()" method.');
        },

        onDragDeselection() {
            debug.warn('Drag-Selector Mixin',
                'You have to override the "onDragDeselection()" method.');
        },

        onMouseDown(originalDomEvent) {
            this.mouseDown = true;
            this.startPoint = {
                x: originalDomEvent.pageX,
                y: originalDomEvent.pageY
            };
            window.addEventListener('mousemove', this._onMouseMove);
            window.addEventListener('mouseup', this._onMouseUp);
        },

        _getScroll() {
            return {
                x: this.$el.scrollLeft || document.body.scrollLeft || document.documentElement.scrollLeft,
                y: this.$el.scrollTop || document.body.scrollTop || document.documentElement.scrollTop
            };
        },

        _onMouseMove(originalDomEvent) {
            if (this.mouseDown) {
                this.endPoint = {
                    x: originalDomEvent.pageX,
                    y: originalDomEvent.pageY
                };
                this._showSelectBox();

                const children = this.$children.length
                    ? this.$children
                    : this.$el.children;
                if (children) {
                    this._handleSelection(children, originalDomEvent);
                }
            }
        },

        _showSelectBox() {
            const selectBox = document.getElementsByClassName('sw-drag-select-box')[0] ||
                document.createElement('div');

            selectBox.setAttribute('class', 'sw-drag-select-box');
            selectBox.setAttribute('style', this._selectionBoxStyling);

            this.$el.appendChild(selectBox);
        },

        _handleSelection(children, originalDomEvent) {
            const newSelection = children.reduce((filtered, item) => {
                if (this._isItemInSelectBox(item.$el)) {
                    this.onDragSelection({
                        originalDomEvent,
                        item
                    });
                    filtered.push(item);
                }

                return filtered;
            }, []);

            this.dragSelection.forEach(item => {
                if (!newSelection.includes(item)) {
                    this.onDragDeselection({
                        originalDomEvent,
                        item
                    });
                }
            });
            this.dragSelection = newSelection;
        },

        _onMouseUp() {
            window.removeEventListener('mousemove', this._onMouseMove);
            window.removeEventListener('mouseup', this._onMouseUp);

            const selectBox = document.getElementsByClassName('sw-drag-select-box')[0];
            if (selectBox) {
                this.$el.removeChild(selectBox);
            }

            this.mouseDown = false;
            this.startPoint = null;
            this.endPoint = null;
        },

        _isItemInSelectBox(el) {
            if (el.classList.contains(this.dragSelectorClass)) {
                const scroll = this._getScroll();
                const element = {
                    top: el.offsetTop - scroll.y,
                    left: el.offsetLeft - scroll.x,
                    width: el.clientWidth,
                    height: el.clientHeight
                };

                return (
                    this._selectionBox.left <= element.left + element.width &&
                    this._selectionBox.left + this._selectionBox.width >= element.left &&
                    this._selectionBox.top <= element.top + element.height &&
                    this._selectionBox.top + this._selectionBox.height >= element.top
                );
            }

            return false;
        }
    }
});
