import { TranslationKey, TranslateFn } from '@shopware-ag/acceptance-test-suite';
import { enNamespaces } from '../locales';

export type CustomTranslationKey = TranslationKey<typeof enNamespaces>;

export type CustomTranslateFn = TranslateFn<CustomTranslationKey>;
