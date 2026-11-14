/**
 * @sw-package framework
 */
import Component from './sw-comp.vue';

// Long enough on purpose: its compiled code runs past the SFC's error line, so its sourcemap can map that line.
type Registration = { name: string; component: unknown; registeredAt: number };

const registrations: Registration[] = [];

function register(name: string, component: unknown): Registration {
    const registration = { name, component, registeredAt: Date.now() };

    registrations.push(registration);

    return registration;
}

function isRegistered(name: string): boolean {
    return registrations.some((registration) => registration.name === name);
}

register('sw-comp', Component);

// eslint-disable-next-line no-console
console.log(isRegistered('sw-comp'), registrations.length);
