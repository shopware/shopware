<?php declare(strict_types=1);

return [
    'menuitem_language-selection' => 'Inicio',
    'menuitem_requirements' => 'Requisitos del sistema',
    'menuitem_database-configuration' => 'Base de datos',
    'menuitem_database-import' => 'Instalación',
    'menuitem_edition' => 'Licencia de Shopware',
    'menuitem_configuration' => 'Configuración',
    'menuitem_finish' => 'Listo',
    'menuitem_license' => 'Términos y condiciones',

    'license_incorrect' => 'Parece que la clave de licencia introducida no es válida',
    'license_does_not_match' => 'La clave de licencia introducida no coincide con ninguna versión comercial de Shopware',
    'license_domain_error' => 'La clave de licencia introducida no es válida para el dominio: ',

    'version_text' => 'Versión:',
    'back' => 'Atrás',
    'forward' => 'Siguiente',
    'start' => 'Iniciar',
    'start_installation' => 'Iniciar instalación',

    'select_language_de' => 'Deutsch',
    'select_language_en' => 'English',
    'select_language_nl' => 'Nederlands',
    'select_language_it' => 'Italiano',
    'select_language_fr' => 'Français',
    'select_language_es' => 'Español',
    'select_language_pt' => 'Português',
    'select_language_pl' => 'Polski',

    'language-selection_select_language' => 'Asistente de instalación de idioma',
    'language-selection_header' => 'Tu instalación de Shopware',
    'language-selection_info_message' => 'El idioma que debe seleccionarse aquí es únicamente para el asistente de instalación; el idioma de tu tienda puedes definirlo en otro momento.',
    'language-selection_welcome_message' => <<<EOT
<p>
    Nos alegramos de que quieras formar parte de la fantástica comunidad mundial de Shopware.
</p>
<p>
    Te acompañaremos paso a paso por el proceso de instalación. Si tienes preguntas, echa un vistazo a nuestro <a href="https://forum.shopware.com" target="_blank">Foro</a>, llámanos al <a href="tel:0080074676260">00 800 746 7626 0 (gratis)</a> o envíanos un <a href="mailto:info@shopware.com">correo electrónico</a>.
</p>
<p>
    <strong>Vamos allá</strong>
</p>
EOT
    ,
    'requirements_header' => 'Requisitos del sistema',
    'requirements_header_files' => 'Archivos y directorios',
    'requirements_header_system' => 'Sistema',
    'requirements_files_info' => 'Debes crear los siguientes archivos y directorios, y tener derechos de escritura',
    'requirements_tablefiles_colcheck' => 'Archivo/Directorio',
    'requirements_tablefiles_colstatus' => 'Estado',
    'requirements_error' => '<h3 class="alert-heading">¡Atención!</h3>No se cumplen todos los requisitos necesarios para una instalación correcta',
    'requirements_success' => '<h3 class="alert-heading">¡Felicidades!</h3>Se cumplen todos los requisitos necesarios para una instalación correcta',
    'requirements_php_info' => 'Tu servidor debe cumplir los siguientes requisitos del sistema para que Shopware sea ejecutable',
    'requirements_php_max_compatible_version' => 'sta versión de Shopware soporta PHP hasta la versión %s. No se puede garantizar la funcionalidad completa con las versiones más nuevas de PHP.',
    'requirements_system_colcheck' => 'Requisito',
    'requirements_system_colrequired' => 'Obligatorio',
    'requirements_system_colfound' => 'Tu sistema',
    'requirements_system_colstatus' => 'Estado',
    'requirements_show_all' => '(mostrar todo)',
    'requirements_hide_all' => '(ocultar todo)',

    'license_agreement_header' => 'Contrato de licencia de usuario final (EULA)',
    'license_agreement_info' => 'Aquí encontrarás nuestro contrato de licencia, que debes leer y aceptar para poder llevar a cabo la instalación. Shopware Community Edition tiene licencia de AGPL, mientras que partes de los complementos y la plantilla cuentan con la licencia "New BSD".',
    'license_agreement_error' => 'Debes aceptar nuestro contrato de licencia',
    'license_agreement_checkbox' => 'Acepto el contrato de licencia',

    'database-configuration_header' => 'Configurar la base de datos',
    'database-configuration_field_host' => 'Servidor de la base de datos:',
    'database-configuration_advanced_settings' => 'Mostrar más ajustes',
    'database-configuration_field_port' => 'Puerto de la base de datos:',
    'database-configuration_field_socket' => 'Socket de la base de datos (opcional):',
    'database-configuration_field_user' => 'Usuario de la base de datos:',
    'database-configuration_field_password' => 'Contraseña de la base de datos:',
    'database-configuration_field_database' => 'Nombre de la base de datos:',
    'database-configuration_field_new_database' => 'New database:',
    'database-configuration_info' => 'Para poder instalar Shopware en tu sistema se requieren los datos de acceso a la base de datos. Si no estás seguro de los datos que debes introducir, ponte en contacto con tu administrador o proveedor de servicios de hosting.',
    'database-configuration-create_new_database' => 'Crear nueva base de datos',

    'database-import_header' => 'Instalación',
    'database-import_skip_import' => 'Omitir',
    'database-import_progress' => 'Progreso: ',
    'database-import-hint' => '<strong>Nota: </strong> En el caso de que ya haya tablas de Shopware en la base de datos configurada, estas se eliminarán con la instalación o actualización.',
    'migration_counter_text_migrations' => 'Se actualizará la base de datos',
    'migration_counter_text_snippets' => 'Se actualizarán los bloques de texto',
    'migration_update_success' => 'La base de datos se ha importado correctamente.',

    'edition_header' => '¿Has adquirido una licencia de Shopware?',
    'edition_info' => 'Shopware está disponible en la versión gratuita <a href="https://en.shopware.com/pricing/" target="_blank">Community Edition </a> y en las versiones de pago <a href="https://en.shopware.com/pricing/" target="_blank">Professional, Professional Plus y Enterprise</a>.',
    'edition_ce' => 'No, quiero utilizar la versión gratuita <a href="https://en.shopware.com/pricing/" target="_blank">Community Edition</a>.',
    'edition_cm' => 'Sí, tengo una licencia de Shopware de pago (<a href="https://en.shopware.com/pricing/" target="_blank">Professional, Professional Plus o Enterprise</a>).',
    'edition_license' => 'Entonces, registra aquí tu clave de licencia. La encontrarás en tu cuenta de Shopware en "Licencias" &rarr; "Licencias de productos" &rarr; "Detalles / Descargar":',
    'edition_license_error' => 'Para la instalación de una versión de Shopware de pago se requiere una licencia válida.',

    'configuration_header' => 'Configuración inicial de la tienda',
    'configuration_sconfig_text' => '¡Ya casi lo tienes! Ahora solo debes definir algunos ajustes básicos para tu tienda y habrás finalizado la instalación. Todo lo que introduzcas aquí lo puedes modificar siempre que quieras.',
    'configuration_sconfig_name' => 'Nombre de tu tienda:',
    'configuration_sconfig_name_info' => 'Introduce el nombre de tu tienda',
    'configuration_sconfig_mail' => 'Dirección de correo electrónico de la tienda:',
    'configuration_sconfig_mail_info' => 'Introduce tu dirección de correo electrónico para los correos electrónicos salientes',
    'configuration_sconfig_domain' => 'Dominio de la tienda:',
    'configuration_sconfig_language' => 'Idioma principal:',
    'configuration_sconfig_currency' => 'Moneda predeterminada:',
    'configuration_sconfig_currency_info' => 'Esta moneda se utilizará de forma predeterminada al definir los precios de los artículos',
    'configuration_admin_currency_eur' => 'Euro',
    'configuration_admin_currency_usd' => 'Dólar (EE. UU.)',
    'configuration_admin_currency_gbp' => 'Libra esterlina (Reino Unido)',
    'configuration_admin_username' => 'Nombre de inicio de sesión del administrador:',
    'configuration_admin_mail' => 'Dirección de correo electrónico del administrador:',
    'configuration_admin_firstName' => 'first name:',
    'configuration_admin_lastName' => 'last name:',

    'configuration_admin_language_de' => 'Alemán',
    'configuration_admin_language_en' => 'Inglés',
    'configuration_admin_password' => 'Contraseña del administrador:',

    'finish_header' => 'Instalación finalizada',
    'finish_info' => 'Has instalado Shopware correctamente.',
    'finish_info_heading' => '¡Hurra!',
    'finish_first_steps' => 'Guía de "primeros pasos"',
    'finish_frontend' => 'Ir al front-end de la tienda',
    'finish_backend' => 'Ir al back-end de la tienda (administración)',
    'finish_message' => '
<p>
    <strong>Bienvenido a Shopware:</strong>
</p>
<p>
    Nos complace poder darte la bienvenida a nuestra comunidad. Has instalado Shopware correctamente.
<p>Ya puedes empezar a utilizar tu tienda. Si eres nuevo en Shopware, te recomendamos que leas la guía <a href="https://docs.shopware.com/en/shopware-5-en/first-steps/first-steps-in-shopware" target="_blank">"Primeros pasos en Shopware"</a>. Cuando te registres por primera vez en el back-end de la tienda, nuestro asistente de instalación te guiará para que definas los ajustes básicos.</p>
<p>¡Disfruta mucho de tu nueva tienda online!</p>',
];
