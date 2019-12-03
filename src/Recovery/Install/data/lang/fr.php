<?php declare(strict_types=1);

return [
    'menuitem_language-selection' => 'Démarrage',
    'menuitem_requirements' => 'Configuration requise',
    'menuitem_database-configuration' => 'Base de données',
    'menuitem_database-import' => 'Installation',
    'menuitem_edition' => 'Licence Shopware',
    'menuitem_configuration' => 'Configuration',
    'menuitem_finish' => 'Terminé',
    'menuitem_license' => 'Conditions générales',

    'license_incorrect' => 'Échec de la vérification de la clé de licence saisie',
    'license_does_not_match' => 'La clé de licence saisie ne correspond à aucune version Shopware commerciale',
    'license_domain_error' => 'La clé de licence saisie n\'est pas valable pour ce domaine: ',

    'version_text' => 'Version:',
    'back' => 'Retour',
    'forward' => 'Continuer',
    'start' => 'Démarrer',
    'start_installation' => 'Démarrer l\'installation',

    'select_language_de' => 'Deutsch',
    'select_language_en' => 'English',
    'select_language_nl' => 'Nederlands',
    'select_language_it' => 'Italiano',
    'select_language_fr' => 'Français',
    'select_language_es' => 'Español',
    'select_language_pt' => 'Português',
    'select_language_pl' => 'Polski',

    'language-selection_select_language' => 'Assistant d\'installation des langues',
    'language-selection_header' => 'Ton installation Shopware',
    'language-selection_info_message' => 'La langue à sélectionner ici se rapporte uniquement aux assistants d\'installation; tu pourras définir la langue de ta boutique ultérieurement.',
    'language-selection_welcome_message' => <<<EOT
<p>
    Notre formidable communauté Shopware mondiale se réjouit de ta venue.
</p>
<p>
    Nous t'accompagnons pas à pas tout au long du processus d'installation. Pour tous renseignements, consulte notre <a href="https://forum.shopware.com" target="_blank">Forum</a> ou contacte-nous par téléphone au <a href="tel:0080074676260">00 800 746 7626 0 (gratuit)</a> ou par <a href="mailto:info@shopware.com">e-mail</a>.
</p>
<p>
    <strong>C'est parti</strong>
</p>
EOT
    ,
    'requirements_header' => 'Configuration requise',
    'requirements_header_files' => 'Fichiers et répertoires',
    'requirements_header_system' => 'Système',
    'requirements_files_info' => 'Les fichiers et répertoires suivants doivent être disponibles et posséder des droits d\'écriture',
    'requirements_tablefiles_colcheck' => 'Fichier/Répertoire',
    'requirements_tablefiles_colstatus' => 'Statut',
    'requirements_error' => '<h3 class="alert-heading">Attention!</h3>L\'installation ne peut être réalisée car la configuration requise n\'est pas satisfaite',
    'requirements_success' => '<h3 class="alert-heading">Félicitations!</h3>L\'installation peut être réalisée car la configuration requise est satisfaite',
    'requirements_php_info' => 'Afin que Shopware soit fonctionnel, ton serveur doit disposer de la configuration requise suivante',
    'requirements_php_max_compatible_version' => 'Cette version de Shopware supporte PHP jusqu\'à la version %s. La fonctionnalité complète avec les plus nouvelles versions de PHP ne peut être garantie.',
    'requirements_system_colcheck' => 'Configuration requise',
    'requirements_system_colrequired' => 'Nécessaire',
    'requirements_system_colfound' => 'Ton système',
    'requirements_system_colstatus' => 'Statut',
    'requirements_show_all' => '(Tout afficher)',
    'requirements_hide_all' => '(Tout masquer)',

    'license_agreement_header' => 'Conditions générales de vente ("CGV")',
    'license_agreement_info' => 'Tu dois lire et accepter ce conditions pour pouvoir compléter l\'installation. L\'édition Shopware Community possède une licence AGPL alors qu\'une partie des plugins et du template dépendent de la nouvelle licence BSD.',
    'license_agreement_error' => 'Vous devez accepter notre conditions générales de vente',
    'license_agreement_checkbox' => 'J\'accepte le contrat de conditions',

    'database-configuration_header' => 'Configurer la base de données',
    'database-configuration_field_host' => 'Serveur de la base de données:',
    'database-configuration_advanced_settings' => 'Afficher les paramètres avancés',
    'database-configuration_field_port' => 'Port de la base de données:',
    'database-configuration_field_socket' => 'Prise de la base de données (facultatif):',
    'database-configuration_field_user' => 'Utilisateur de la base de données:',
    'database-configuration_field_password' => 'Mot de passe de la base de données:',
    'database-configuration_field_database' => 'Nom de la base de données:',
    'database-configuration_field_new_database' => 'New database:',
    'database-configuration_info' => 'Pour installer Shopware sur ton système, les informations de connexion à la base de données sont nécessaires. Si tu n\'es pas sûr de ce que tu dois saisir, contacte ton administrateur/hôte.',
    'database-configuration-create_new_database' => 'Créer une nouvelle base de données',

    'database-import_header' => 'Installation',
    'database-import_skip_import' => 'Passer',
    'database-import_progress' => 'Progression: ',
    'database-import-hint' => '<strong>Remarque: </strong> si des tableaux Shopware se trouvent déjà dans la base de données configurée, ils seront supprimés lors de l\'installation/la mise à jour!',
    'migration_counter_text_migrations' => 'Mise à jour de la base de données',
    'migration_counter_text_snippets' => 'Mise à jour des modules de textes',
    'migration_update_success' => 'Base de données importée!',

    'edition_header' => 'As-tu obtenu une licence Shopware?',
    'edition_info' => 'Il existe une édition Shopware <a href="https://en.shopware.com/pricing/" target="_blank">Community</a> gratuite ainsi que des éditions payantes <a href="https://en.shopware.com/pricing/" target="_blank">Professional, Professional Plus et Enterprise</a>.',
    'edition_ce' => 'Je souhaite utiliser l\'édition <a href="https://en.shopware.com/pricing/" target="_blank">Community</a> gratuite.',
    'edition_cm' => 'Je possède une licence Shopware payante (<a href="https://en.shopware.com/pricing/" target="_blank">Professional, Professional Plus ou Enterprise</a>).',
    'edition_license' => 'Saisis ici ta clé de licence. Tu la trouveras sur ton compte Shopware sous "Licences"; "Licences de produits"; "Détails/Téléchargement":',
    'edition_license_error' => 'Il est nécessaire de posséder une licence valable pour installer une version Shopware payante.',

    'configuration_header' => 'Configuration de base de la boutique',
    'configuration_sconfig_text' => 'Presque terminé! Pour terminer l\'installation, tu dois déterminer les paramètres de base de ta boutique. Tu peux modifier ultérieurement tout ce que tu saisis ici!',
    'configuration_sconfig_name' => 'Nom de ta boutique:',
    'configuration_sconfig_name_info' => 'Saisis ici le nom de ta boutique',
    'configuration_sconfig_mail' => 'Adresse e-mail de ta boutique:',
    'configuration_sconfig_mail_info' => 'Saisis ici ton adresse e-mail pour les e-mails sortants',
    'configuration_sconfig_domain' => 'Domaine de ta boutique:',
    'configuration_sconfig_language' => 'Langue principale:',
    'configuration_sconfig_currency' => 'Devise par défaut:',
    'configuration_sconfig_currency_info' => 'Cette devise sera utilisée par défaut pour définir le prix d\'un article',
    'configuration_admin_currency_eur' => 'Euro',
    'configuration_admin_currency_usd' => 'Dollar (US)',
    'configuration_admin_currency_gbp' => 'Livre britannique (GB)',
    'configuration_admin_username' => 'Identifiant de l\'administrateur:',
    'configuration_admin_mail' => 'E-mail de l\'administrateur:',
    'configuration_admin_firstName' => 'first name:',
    'configuration_admin_lastName' => 'last name:',

    'configuration_admin_language_de' => 'Allemand',
    'configuration_admin_language_en' => 'Anglais',
    'configuration_admin_password' => 'Mot de passe de l\'administrateur:',

    'finish_header' => 'Installation terminée',
    'finish_info' => 'Shopware a été installé!',
    'finish_info_heading' => 'Ouah!',
    'finish_first_steps' => '"Premiers pas" - Guide',
    'finish_frontend' => 'Vers le frontend de la boutique',
    'finish_backend' => 'Vers le backend de la boutique (Administrateur)',
    'finish_message' => '
<p>
    <strong>Bienvenue sur Shopware,</strong>
</p>
<p>
    Nous nous réjouissons de t\'accueillir dans notre communauté. Shopware a été installé.
<p>Ta boutique est maintenant opérationnelle. Si tu utilises Shopware pour la première fois, nous te conseillons de consulter le guide <a href="https://docs.shopware.com/en/shopware-5-en/first-steps/first-steps-in-shopware" target="_blank">"Premiers pas sur Shopware"</a>. Si tu te connectes pour la première fois au backend de la boutique, notre "First Run Wizard" t\'accompagnera à travers l\'installation de base.</p>
<p>Profite bien de ta nouvelle boutique en ligne!</p>',
];
