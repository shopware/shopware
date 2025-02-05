/**
 * @sw-package framework
 */
import { ref } from 'vue';

const locales = ref<string[]>([]);

const registerAdminLocale = (locale: string) => {
    if (locales.value.find((l) => l === locale)) {
        return;
    }

    locales.value.push(locale);
};

/**
 * @private
 */
export default function useSystem() {
    return {
        locales,
        registerAdminLocale,
    };
}
