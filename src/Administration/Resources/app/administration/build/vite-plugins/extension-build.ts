/**
 * @sw-package framework
 */
import { main } from '../plugins.vite';

void main().catch((error: unknown) => {
    console.error(error);

    if (process.env.VITE_MODE === 'production') {
        process.exit(1);
    }
});
