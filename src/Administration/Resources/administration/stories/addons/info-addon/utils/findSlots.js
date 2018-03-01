function removeDuplicates(myArr, prop) {
    return myArr.filter((obj, pos, arr) => {
        return arr.map(mapObj => mapObj[prop]).indexOf(obj[prop]) === pos;
    });
}

function findSlotNameBasedOnAttribute(slot, tag) {
    const slotAttributes = slot.attributes;
    let slotName = 'default';

    // The "slot"-tag has a "name" attribute
    if (Object.prototype.hasOwnProperty.call(slotAttributes, 'name')) {
        slotName = slotAttributes.name.value;
    }

    if (tag !== 'slot') {
        // Scoped slots are using the "slot" attribute for the name of the slot
        if (Object.prototype.hasOwnProperty.call(slotAttributes, 'slot')) {
            slotName = slotAttributes.slot.value;
        }
    }

    return slotName;
}

function findScopedSlotVariableBasedOnAttribute(slot) {
    const slotAttributes = slot.attributes;
    let scopedSlotVariable = null;

    if (Object.prototype.hasOwnProperty.call(slotAttributes, 'slot-scope')) {
        scopedSlotVariable = slotAttributes['slot-scope'].value;
    }

    return scopedSlotVariable;
}

export default function findSlots(template) {
    let slots = [];
    if (!template) {
        return slots;
    }
    const doc = new DOMParser().parseFromString(template, 'text/html');
    const staticSlots = doc.querySelectorAll('slot');
    const scopedSlots = doc.querySelectorAll('*[slot-scope]');

    const domSlots = [...staticSlots, ...scopedSlots];

    domSlots.forEach((slot) => {
        const tag = slot.nodeName.toLowerCase();
        const name = findSlotNameBasedOnAttribute(slot, tag);
        let scopedSlot = false;

        const slotVariable = findScopedSlotVariableBasedOnAttribute(slot);
        if (slotVariable) {
            scopedSlot = true;
        }

        slots.push({
            tag,
            name,
            scopedSlot,
            slotVariable: (slotVariable || 'null')
        });
    });

    slots = removeDuplicates(slots, 'name');

    return slots;
}
