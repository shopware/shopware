/**
 * @sw-package framework
 */
import { computed, ref } from 'vue';
import useSystem from './use-system';

interface LocaleToLanguageService {
    localeToLanguage(locale: string): Promise<string>;
}

const currentUser = ref<EntitySchema.user | null>(null);
const userPending = computed(() => !currentUser.value);
const languageId = ref('');
const currentLocale = ref<string | null>(null);

const adminLocaleLanguage = computed(() => currentLocale.value?.split('-')[0] ?? null);

const adminLocaleRegion = computed(() => currentLocale.value?.split('-')[1] ?? null);

const userPrivileges = computed(() => currentUser.value?.aclRoles?.map((role) => role.privileges).flat() ?? []);

async function setAdminLocale(locale: string): Promise<void> {
    const { locales } = useSystem();
    const loginService = Shopware.Service('loginService');

    if (!loginService?.isLoggedIn()) {
        setAdminLocaleState({ locales: locales.value, locale, languageId: '' });
        return Promise.resolve();
    }

    const localeToLanguageService = Shopware.Service('localeToLanguageService') as LocaleToLanguageService;
    return localeToLanguageService.localeToLanguage(locale).then((languageIdFromService) => {
        setAdminLocaleState({ locales: locales.value, locale, languageId: languageIdFromService });
        Shopware.Application.getContainer('factory').locale.storeCurrentLocale(locale);
    });
}

function setCurrentUser(user: EntitySchema.user) {
    currentUser.value = user;
}

function removeCurrentUser() {
    currentUser.value = null;
}

function setAdminLocaleState({
    locales,
    locale,
    languageId: paramLanguageId,
}: {
    locales: string[];
    locale: string;
    languageId: string;
}) {
    if (!locales.find((l) => l === locale)) {
        Shopware.Utils.debug.warn('SessionStore', `Locale ${locale} not registered at store`);
        return;
    }

    languageId.value = paramLanguageId;
    currentLocale.value = locale;
}

/**
 * @private
 */
export default function useSession() {
    return {
        currentUser,
        userPending,
        languageId,
        currentLocale,
        adminLocaleLanguage,
        adminLocaleRegion,
        userPrivileges,
        setAdminLocale,
        setCurrentUser,
        removeCurrentUser,
        setAdminLocaleState,
    };
}
