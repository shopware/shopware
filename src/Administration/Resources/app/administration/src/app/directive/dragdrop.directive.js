const { Directive } = Shopware;
const { types } = Shopware.Utils;

/**
 * @description An object representing the current drag element and config.
 * @type {?{ el: HTMLElement, dragConfig: DragConfig }}
 */
let currentDrag = null;

/**
 * @description An object representing the current drop zone element and config.
 * @type {?{ el: HTMLElement, dropConfig: DropConfig }}
 */
let currentDrop = null;

/**
 * @description The proxy element which is used to display the moved element.
 * @type {?(HTMLElement|Node)}
 */
let dragElement = null;

/**
 * @description The x offset of the mouse position inside the dragged element.
 * @type {number}
 */
let dragMouseOffsetX = 0;

/**
 * @description The y offset of the mouse position inside the dragged element.
 * @type {number}
 */
let dragMouseOffsetY = 0;

/**
 * @description The timeout managing the delayed drag start.
 * @type {?number}
 */
let delayTimeout = null;

/**
 * @description A registry of all drop zones.
 * @type {Array}
 */
const dropZones = [];

/**
 * The default config for the draggable directive.
 *
 * @typedef {object} DragConfig
 * @property {?number} delay
 * @property {(number|string)} dragGroup
 * @property {string} draggableCls
 * @property {string} draggingStateCls
 * @property {string} dragElementCls
 * @property {string} validDragCls
 * @property {string} invalidDragCls
 * @property {boolean} preventEvent
 * @property {?function} validateDrop
 * @property {?function} onDragStart
 * @property {?function} onDragEnter
 * @property {?function} onDragLeave
 * @property {?function} onDrop
 * @property {?object} data
 */
const defaultDragConfig = {
    delay: 100,
    dragGroup: 1,
    draggableCls: 'is--draggable',
    draggingStateCls: 'is--dragging',
    dragElementCls: 'is--drag-element',
    validDragCls: 'is--valid-drag',
    invalidDragCls: 'is--invalid-drag',
    preventEvent: true,
    validateDrop: null,
    validateDrag: null,
    onDragStart: null,
    onDragEnter: null,
    onDragLeave: null,
    onDrop: null,
    data: null,
    disabled: false,
};

/**
 * The default config for the droppable directive.
 *
 * @typedef {object} DropConfig
 * @property {(number|string)} dragGroup
 * @property {string} droppableCls
 * @property {string} validDropCls
 * @property {string} invalidDropCls
 * @property {?function} validateDrop
 * @property {?function} onDrop
 * @property {?object} data
 */
const defaultDropConfig = {
    dragGroup: 1,
    droppableCls: 'is--droppable',
    validDropCls: 'is--valid-drop',
    invalidDropCls: 'is--invalid-drop',
    validateDrop: null,
    onDrop: null,
    data: null,
};

/**
 * Fired by event callback when the user starts dragging an element.
 *
 * @param {HTMLElement} el
 * @param {DragConfig} dragConfig
 * @param {(MouseEvent|TouchEvent)} event
 * @return {boolean}
 */
function onDrag(el, dragConfig, event) {
    if (event.buttons !== 1) {
        return false;
    }
    if (dragConfig.preventEvent === true) {
        event.preventDefault();
        event.stopPropagation();
    }
    if (dragConfig.delay === null || dragConfig.delay <= 0) {
        startDrag(el, dragConfig, event);
    } else {
        delayTimeout = window.setTimeout(startDrag.bind(this, el, dragConfig, event), dragConfig.delay);
    }

    document.addEventListener('mouseup', stopDrag);
    document.addEventListener('touchend', stopDrag);

    return true;
}

/**
 * Initializes the drag state for the current drag action.
 *
 * @param {HTMLElement|HTMLDivElement} el
 * @param {DragConfig} dragConfig
 * @param {(MouseEvent|TouchEvent)} event
 */
function startDrag(el, dragConfig, event) {
    delayTimeout = null;

    if (currentDrag !== null) {
        return;
    }

    currentDrag = { el, dragConfig };

    const elBoundingBox = el.getBoundingClientRect();

    const pageX = event.pageX || event.touches[0].pageX;
    const pageY = event.pageY || event.touches[0].pageY;

    dragMouseOffsetX = pageX - elBoundingBox.left;
    dragMouseOffsetY = pageY - elBoundingBox.top;

    dragElement = el.cloneNode(true);
    dragElement.classList.add(dragConfig.dragElementCls);
    dragElement.style.width = `${elBoundingBox.width}px`;
    dragElement.style.left = `${pageX - dragMouseOffsetX}px`;
    dragElement.style.top = `${pageY - dragMouseOffsetY}px`;
    document.body.appendChild(dragElement);

    el.classList.add(dragConfig.draggingStateCls);

    if (types.isFunction(currentDrag.dragConfig.onDragStart)) {
        currentDrag.dragConfig.onDragStart(currentDrag.dragConfig, el, dragElement);
    }

    document.addEventListener('mousemove', moveDrag);
    document.addEventListener('touchmove', moveDrag);
}

/**
 * Fired by event callback when the user moves the dragged element.
 *
 * @param {(MouseEvent|TouchEvent)} event
 */
function moveDrag(event) {
    if (currentDrag === null) {
        stopDrag();
        return;
    }

    const pageX = event.pageX || event.touches[0].pageX;
    const pageY = event.pageY || event.touches[0].pageY;

    dragElement.style.left = `${pageX - dragMouseOffsetX}px`;
    dragElement.style.top = `${pageY - dragMouseOffsetY}px`;

    if (event.type === 'touchmove') {
        dropZones.forEach((zone) => {
            if (isEventOverElement(event, zone.el)) {
                if (currentDrop === null || zone.el !== currentDrop.el) {
                    enterDropZone(zone.el, zone.dropConfig);
                }
            } else if (currentDrop !== null && zone.el === currentDrop.el) {
                leaveDropZone(zone.el, zone.dropConfig);
            }
        });
    }
}

/**
 * Helper method for detecting if the current event position
 * is in the boundaries of an existing drop zone element.
 *
 * @param {(MouseEvent|TouchEvent)} event
 * @param {HTMLElement} el
 * @return {boolean}
 */
function isEventOverElement(event, el) {
    const pageX = event.pageX || event.touches[0].pageX;
    const pageY = event.pageY || event.touches[0].pageY;

    const box = el.getBoundingClientRect();

    return pageX >= box.x && pageX <= (box.x + box.width) &&
        pageY >= box.y && pageY <= (box.y + box.height);
}

/**
 * Stops all drag interaction and resets all variables and listeners.
 */
function stopDrag() {
    if (delayTimeout !== null) {
        window.clearTimeout(delayTimeout);
        delayTimeout = null;
        return;
    }

    const validDrag = validateDrag();
    const validDrop = validateDrop();

    if (validDrag === true) {
        if (types.isFunction(currentDrag.dragConfig.onDrop)) {
            currentDrag.dragConfig.onDrop(
                currentDrag.dragConfig.data,
                validDrop ? currentDrop.dropConfig.data : null,
            );
        }
    }

    if (validDrop === true) {
        if (types.isFunction(currentDrop.dropConfig.onDrop)) {
            currentDrop.dropConfig.onDrop(currentDrag.dragConfig.data, currentDrop.dropConfig.data);
        }
    }

    document.removeEventListener('mousemove', moveDrag);
    document.removeEventListener('touchmove', moveDrag);

    document.removeEventListener('mouseup', stopDrag);
    document.removeEventListener('touchend', stopDrag);

    if (dragElement !== null) {
        dragElement.remove();
        dragElement = null;
    }

    if (currentDrag !== null) {
        currentDrag.el.classList.remove(currentDrag.dragConfig.draggingStateCls);
        currentDrag.el.classList.remove(currentDrag.dragConfig.validDragCls);
        currentDrag.el.classList.remove(currentDrag.dragConfig.invalidDragCls);
        currentDrag = null;
    }

    if (currentDrop !== null) {
        currentDrop.el.classList.remove(currentDrop.dropConfig.validDropCls);
        currentDrop.el.classList.remove(currentDrop.dropConfig.invalidDropCls);
        currentDrop = null;
    }

    dragMouseOffsetX = 0;
    dragMouseOffsetY = 0;
}

/**
 * Fired by event callback when the user moves the dragged element over an existing drop zone.
 *
 * @param {HTMLElement} el
 * @param {DropConfig} dropConfig
 */
function enterDropZone(el, dropConfig) {
    if (currentDrag === null) {
        return;
    }
    currentDrop = { el, dropConfig };

    const valid = validateDrop();

    if (valid === true) {
        el.classList.add(dropConfig.validDropCls);
        el.classList.remove(dropConfig.invalidDropCls);
        dragElement.classList.add(currentDrag.dragConfig.validDragCls);
        dragElement.classList.remove(currentDrag.dragConfig.invalidDragCls);
    } else {
        el.classList.add(dropConfig.invalidDropCls);
        el.classList.remove(dropConfig.validDropCls);
        dragElement.classList.add(currentDrag.dragConfig.invalidDragCls);
        dragElement.classList.remove(currentDrag.dragConfig.validDragCls);
    }

    if (types.isFunction(currentDrag.dragConfig.onDragEnter)) {
        currentDrag.dragConfig.onDragEnter(currentDrag.dragConfig.data, currentDrop.dropConfig.data, valid);
    }
}

/**
 * Fired by event callback when the user moves the dragged element out of an existing drop zone.
 *
 * @param {HTMLElement} el
 * @param {DropConfig} dropConfig
 */
function leaveDropZone(el, dropConfig) {
    if (currentDrag === null) {
        return;
    }

    if (types.isFunction(currentDrag.dragConfig.onDragLeave)) {
        currentDrag.dragConfig.onDragLeave(currentDrag.dragConfig.data, currentDrop.dropConfig.data);
    }

    el.classList.remove(dropConfig.validDropCls);
    el.classList.remove(dropConfig.invalidDropCls);
    dragElement.classList.remove(currentDrag.dragConfig.validDragCls);
    dragElement.classList.remove(currentDrag.dragConfig.invalidDragCls);

    currentDrop = null;
}

/**
 * Validates a drop using the {currentDrag} and {currentDrop} configuration.
 * Also calls the custom validator functions of the two configs.
 *
 * @return {boolean}
 */
function validateDrop() {
    let valid = true;
    let customDragValidation = true;
    let customDropValidation = true;

    // Validate if the drag and drop are using the same drag group.
    if (currentDrag === null ||
        currentDrop === null ||
        currentDrop.dropConfig.dragGroup !== currentDrag.dragConfig.dragGroup) {
        valid = false;
    }

    // Check the custom drag validate function.
    if (currentDrag !== null && types.isFunction(currentDrag.dragConfig.validateDrop)) {
        customDragValidation = currentDrag.dragConfig.validateDrop(currentDrag.dragConfig.data, currentDrop.dropConfig.data);
    }

    // Check the custom drop validate function.
    if (currentDrop !== null && types.isFunction(currentDrop.dropConfig.validateDrop)) {
        customDropValidation = currentDrop.dropConfig.validateDrop(currentDrag.dragConfig.data, currentDrop.dropConfig.data);
    }

    return valid && customDragValidation && customDropValidation;
}
/**
 * Validates a drag using the {currentDrag} configuration.
 * Also calls the custom validator functions of the config.
 *
 * @return {boolean}
 */
function validateDrag() {
    let valid = true;
    let customDragValidation = true;

    // Validate if the drag and drop are using the same drag group.
    if (currentDrag === null) {
        valid = false;
    }

    // Check the custom drag validate function.
    if (currentDrag !== null && types.isFunction(currentDrag.dragConfig.validateDrag)) {
        customDragValidation = currentDrag.dragConfig.validateDrag(currentDrag.dragConfig.data, currentDrop.dropConfig.data);
    }

    return valid && customDragValidation;
}

function mergeConfigs(defaultConfig, binding) {
    const mergedConfig = Object.assign({}, defaultConfig);

    if (types.isObject(binding.value)) {
        Object.assign(mergedConfig, binding.value);
    } else {
        Object.assign(mergedConfig, { data: binding.value });
    }

    return mergedConfig;
}

/**
 * Directive for making elements draggable.
 *
 * Usage:
 * <div v-draggable="{ data: {...}, onDrop() {...} }"></div>
 *
 * See the {DragConfig} for all possible config options.
 */
Directive.register('draggable', {
    inserted(el, binding) {
        const dragConfig = mergeConfigs(defaultDragConfig, binding);
        el.dragConfig = dragConfig;
        el.boundDragListener = onDrag.bind(this, el, el.dragConfig);

        if (!dragConfig.disabled) {
            el.classList.add(dragConfig.draggableCls);
            el.addEventListener('mousedown', el.boundDragListener);
            el.addEventListener('touchstart', el.boundDragListener);
        }
    },

    update(el, binding) {
        const dragConfig = mergeConfigs(defaultDragConfig, binding);

        if (el.dragConfig.disabled !== dragConfig.disabled) {
            if (dragConfig.disabled === true) {
                el.classList.remove(el.dragConfig.draggableCls);
                el.classList.add(dragConfig.draggableCls);
                el.addEventListener('mousedown', el.boundDragListener);
                el.addEventListener('touchstart', el.boundDragListener);
            } else {
                el.classList.remove(el.dragConfig.draggableCls);
                el.removeEventListener('mousedown', el.boundDragListener);
                el.removeEventListener('touchstart', el.boundDragListener);
            }
        }

        Object.assign(el.dragConfig, dragConfig);
    },

    unbind(el, binding) {
        const dragConfig = mergeConfigs(defaultDragConfig, binding);

        el.classList.remove(dragConfig.draggableCls);

        if (el.boundDragListener) {
            el.removeEventListener('mousedown', el.boundDragListener);
            el.removeEventListener('touchstart', el.boundDragListener);
        }
    },
});

/**
 * Directive to define an element as a drop zone.
 *
 * Usage:
 * <div v-droppable="{ data: {...}, onDrop() {...} }"></div>
 *
 * See the {dropConfig} for all possible config options.
 */
Directive.register('droppable', {
    inserted(el, binding) {
        const dropConfig = mergeConfigs(defaultDropConfig, binding);

        dropZones.push({ el, dropConfig });

        el.classList.add(dropConfig.droppableCls);
        el.addEventListener('mouseenter', enterDropZone.bind(this, el, dropConfig));
        el.addEventListener('mouseleave', leaveDropZone.bind(this, el, dropConfig));
    },

    unbind(el, binding) {
        const dropConfig = mergeConfigs(defaultDropConfig, binding);

        dropZones.splice(dropZones.findIndex(zone => zone.el === el), 1);

        el.classList.remove(dropConfig.droppableCls);
        el.removeEventListener('mouseenter', enterDropZone.bind(this, el, dropConfig));
        el.removeEventListener('mouseleave', leaveDropZone.bind(this, el, dropConfig));
    },

    update: (el, binding) => {
        const dropZone = dropZones.find(zone => zone.el === el);

        if (types.isObject(binding.value)) {
            Object.assign(dropZone.dropConfig, binding.value);
        } else {
            Object.assign(dropZone.dropConfig, { data: binding.value });
        }
    },
});
