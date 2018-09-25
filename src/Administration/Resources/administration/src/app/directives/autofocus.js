import { Directive } from 'src/core/shopware';

Directive.register('autofocus', {
    inserted: (el) => {
        const inputs = el.getElementsByTagName('input');
        if (inputs.length === 0) {
            return;
        }

        inputs[0].focus();
    }
});
