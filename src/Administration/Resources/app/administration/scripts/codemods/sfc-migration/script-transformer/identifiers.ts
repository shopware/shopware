import { ident } from './identifier-template';

export const routerIdent = ident('router', {
    fallback: [
        '$router',
        'vueRouter',
    ],
});

export const routeIdent = ident('route', {
    fallback: [
        '$route',
        'vueRoute',
    ],
});

export const slotsIdent = ident('slots', {
    fallback: [
        '$slots',
        'vueSlots',
    ],
});

export const attrsIdent = ident('attrs', {
    fallback: [
        '$attrs',
        'vueAttrs',
    ],
});

export const tIdent = ident('t', {
    fallback: [
        '$t',
        'translate',
    ],
});

export const emitIdent = ident('emit', {
    fallback: [
        '$emit',
        'vueEmit',
    ],
});
