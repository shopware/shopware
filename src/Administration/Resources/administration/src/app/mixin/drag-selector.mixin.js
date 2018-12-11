import { Mixin } from 'src/core/shopware';
import { debug } from 'src/core/service/util.service';

Mixin.register('drag-selector', {
    data() {
        return {
            mouseDown: false,
            startPoint: null,
            endPoint: null,
            dragSelection: [],
            originalScroll: null
        };
    },

    computed: {
        _dragIsActive() {
            return !this.mouseDown || !this.startPoint || !this.endPoint;
        },

        _selectionBox() {
            if (this._dragIsActive) {
                return null;
            }

            const points = this._getPoints();
            const scroll = this._getScroll();

            const rect = this.scrollContainer().parentNode.getBoundingClientRect();
            const left = Math.min(points.start.x, points.end.x) - rect.left - scroll.x;
            const top = Math.min(points.start.y, points.end.y) - rect.top - scroll.y;
            const width = Math.abs(points.start.x - points.end.x);
            const height = Math.abs(points.start.y - points.end.y);

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
        },

        dragSelectorClass() {
            debug.warn('Drag-Selector Mixin',
                'You have to specify a DragSelectorClass.');
            return '';
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

        scrollContainer() {
            debug.warn('Drag-Selector Mixin',
                'You have to specify a your ScrollContainer element.');
        },

        itemContainer() {
            return false;
        },

        onMouseDown(originalDomEvent) {
            if (!originalDomEvent.ctrlKey && !originalDomEvent.metaKey) {
                return;
            }

            this.mouseDown = true;
            this.startPoint = {
                x: originalDomEvent.pageX,
                y: originalDomEvent.pageY
            };
            this.endPoint = null;

            this.originalScroll = this._getScroll();
            window.addEventListener('mousemove', this._onMouseMove);
            window.addEventListener('mouseup', this._onMouseUp);
        },

        // determines whether a click-events originates from a drag
        isDragEvent(event) {
            return this.endPoint && this.endPoint.x === event.pageX && this.endPoint.y === event.pageY;
        },

        _getPoints() {
            const scroll = this._getScroll();
            const border = this.scrollContainer().getBoundingClientRect();
            const points = {
                start: {
                    x: this.startPoint.x + this.originalScroll.x,
                    y: this.startPoint.y + this.originalScroll.y
                },
                end: {
                    x: this.endPoint.x + scroll.x,
                    y: this.endPoint.y + scroll.y
                }
            };
            points.start.x = Math.max(points.start.x, border.left + scroll.x);
            points.start.x = Math.min(points.start.x, border.right + scroll.x);
            points.start.y = Math.max(points.start.y, border.top + scroll.y);
            points.start.y = Math.min(points.start.y, border.bottom + scroll.y);

            this._scrollLeft(points, border, scroll);
            this._scrollRight(points, border, scroll);
            this._scrollUp(points, border, scroll);
            this._scrollDown(points, border, scroll);

            return points;
        },

        _scrollLeft(points, border, scroll) {
            if (this.endPoint.x < border.left) {
                const distance = (this.endPoint.x - border.left) / 3;
                points.end.x = border.left + scroll.x;
                this.scrollContainer().scrollTo(scroll.x + distance, scroll.y);
                const newScroll = this._getScroll();
                if (newScroll.x !== scroll.x) {
                    points.end.x += newScroll.x - scroll.x;
                }
            }
        },

        _scrollRight(points, border, scroll) {
            if (this.endPoint.x > border.right) {
                const distance = (this.endPoint.x - border.right) / 3;
                points.end.x = border.right + scroll.x;
                this.scrollContainer().scrollTo(scroll.x + distance, scroll.y);
                const newScroll = this._getScroll();
                if (newScroll.x !== scroll.x) {
                    points.end.x += newScroll.x - scroll.x;
                }
            }
        },

        _scrollUp(points, border, scroll) {
            if (this.endPoint.y < border.top) {
                const distance = (this.endPoint.y - border.top) / 3;
                points.end.y = border.top + scroll.y;
                this.scrollContainer().scrollTo(scroll.x, scroll.y + distance);
                const newScroll = this._getScroll();
                if (newScroll.y !== scroll.y) {
                    points.end.y += newScroll.y - scroll.y;
                }
            }
        },

        _scrollDown(points, border, scroll) {
            if (this.endPoint.y > border.bottom) {
                const distance = (this.endPoint.y - border.bottom) / 3;
                points.end.y = border.bottom + scroll.y;
                this.scrollContainer().scrollTo(scroll.x, scroll.y + distance);
                const newScroll = this._getScroll();
                if (newScroll.y !== scroll.y) {
                    points.end.y += newScroll.y - scroll.y;
                }
            }
        },

        _getScroll() {
            return {
                x: this.scrollContainer().scrollLeft || document.body.scrollLeft || document.documentElement.scrollLeft,
                y: this.scrollContainer().scrollTop || document.body.scrollTop || document.documentElement.scrollTop
            };
        },

        _onMouseMove(originalDomEvent) {
            if (this.mouseDown) {
                this.endPoint = {
                    x: originalDomEvent.pageX,
                    y: originalDomEvent.pageY
                };
                this._showSelectBox();

                const children = this.itemContainer() ?
                    this.itemContainer().$children :
                    Array.from(this.scrollContainer().children);
                if (children) {
                    this._handleDragSelection(children, originalDomEvent);
                }
            }
        },

        _showSelectBox() {
            const selectBox = document.getElementsByClassName('sw-drag-select-box')[0] ||
                document.createElement('div');

            selectBox.setAttribute('class', 'sw-drag-select-box');
            selectBox.setAttribute('style', this._selectionBoxStyling);

            this.scrollContainer().appendChild(selectBox);
        },

        _handleDragSelection(children, originalDomEvent) {
            const newSelection = children.reduce((filtered, item) => {
                if (this._isItemInSelectBox((item.$el ? item.$el : item))) {
                    if (!this.dragSelection.includes(item)) {
                        this.onDragSelection({
                            originalDomEvent,
                            item
                        });
                    }
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
                this.scrollContainer().removeChild(selectBox);
            }

            this.mouseDown = false;
            this.startPoint = null;
            this.dragSelection = [];
        },

        _isItemInSelectBox(el) {
            if (el.classList.contains(this.dragSelectorClass)) {
                const scroll = this._getScroll();
                const element = {
                    top: el.offsetTop,
                    left: el.offsetLeft,
                    width: el.clientWidth,
                    height: el.clientHeight
                };

                const rect = this.scrollContainer().parentNode.getBoundingClientRect();
                const left = Math.min(
                    this.startPoint.x + this.originalScroll.x,
                    this.endPoint.x + scroll.x
                ) - rect.left;
                const top = Math.min(
                    this.startPoint.y + this.originalScroll.y,
                    this.endPoint.y + scroll.y
                ) - rect.top;
                const width = Math.abs(
                    (this.startPoint.x + this.originalScroll.x) -
                    (this.endPoint.x + scroll.x)
                );
                const height = Math.abs(
                    (this.startPoint.y + this.originalScroll.y) -
                    (this.endPoint.y + scroll.y)
                );

                return (
                    left <= element.left + element.width &&
                    left + width >= element.left &&
                    top <= element.top + element.height &&
                    top + height >= element.top
                );
            }

            return false;
        }
    }
});
