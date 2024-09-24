/**
 * @package admin
 *
 * This is the initial start file for the whole administration. It loads
 * the Shopware Core with the Shopware object. And then starts to execute
 * the application.
 */
import { configureCompat } from 'vue';
import 'src/core/shopware';
import 'src/app/main';

// Take all keys out of Shopware.compatConfig but set them to true
const compatConfig = Object.fromEntries(Object.keys(Shopware.compatConfig).map(key => [key, true]));

// eslint-disable-next-line @typescript-eslint/no-unsafe-call
configureCompat(compatConfig);
