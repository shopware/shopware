let previousSelectedElement = null;

function setAsMarked(element) {
    element.setAttribute('checked', '');
    element.setAttribute('disabled', '');

    // adding default label by adding 'is--active' class
    const parentElement = element.parentElement;
    const newClasses = parentElement.getAttribute('class') + ' is--selected';
    parentElement.setAttribute('class', newClasses);
}

function setAsNotMarked(element) {
    element.removeAttribute('checked');
    element.removeAttribute('disabled');

    // removing default label by removing 'is--active' class
    const parentElement = element.parentElement;
    const newClasses = parentElement.getAttribute('class').replace('is--selected', '');
    parentElement.setAttribute('class', newClasses);
}

function setElementState(element) {
    const isChecked = element.getAttribute('checked');

    if (previousSelectedElement !== null) {
        setAsNotMarked(previousSelectedElement);

        previousSelectedElement = element;
    } else {
        previousSelectedElement = element;
    }

    if (!isChecked) {
        setAsMarked(element);

        return;
    }

    setAsNotMarked(element);
}

