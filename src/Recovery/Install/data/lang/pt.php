<?php declare(strict_types=1);

return [
    'menuitem_language-selection' => 'Início',
    'menuitem_requirements' => 'Requisitos do sistema',
    'menuitem_database-configuration' => 'Base de dados',
    'menuitem_database-import' => 'Instalação',
    'menuitem_edition' => 'Licença de Shopware',
    'menuitem_configuration' => 'Configuração',
    'menuitem_finish' => 'Terminado',
    'menuitem_license' => 'Termos e Condições ',

    'license_incorrect' => 'Aparentemente o código da licença introduzido não é válido',
    'license_does_not_match' => 'O código de licença introduzido não corresponde a qualquer versão de Shopware comercial',
    'license_domain_error' => 'O código de licença introduzido não é válido para o domínio: ',

    'version_text' => 'Versão:',
    'back' => 'Anterior',
    'forward' => 'Seguinte',
    'start' => 'Iniciar',
    'start_installation' => 'Iniciar instalação',

    'select_language_de' => 'Deutsch',
    'select_language_en' => 'English',
    'select_language_nl' => 'Nederlands',
    'select_language_it' => 'Italiano',
    'select_language_fr' => 'Français',
    'select_language_es' => 'Español',
    'select_language_pt' => 'Português',
    'select_language_pl' => 'Polski',

    'language-selection_select_language' => 'Idioma do assistente de instalação',
    'language-selection_header' => 'A sua instalação do Shopware',
    'language-selection_info_message' => 'O idioma aqui escolhido aplica-se apenas ao assistente de instalação; o idioma da sua loja pode ser definido posteriormente.',
    'language-selection_welcome_message' => <<<EOT
<p>
    Ficamos muito satisfeitos com a sua intenção de integrar a nossa Comunidade Shopware, uma rede mundial impressionante.
</p>
<p>
    Fazemos um acompanhamento passo a passo ao longo de todo o processo de instalação. Caso tenha dúvidas, passe pelo nosso <a href="https://forum.shopware.com" target="_blank">fórum</a> ou contacte-nos telefonicamente através do <a href="tel:0080074676260">00 800 746 7626 0 (grátis)</a> ou por <a href="mailto:info@shopware.com">correio eletrónico</a>.
</p>
<p>
    <strong>Mãos à obra</strong>
</p>
EOT
    ,
    'requirements_header' => 'Requisitos do sistema',
    'requirements_header_files' => 'Ficheiros e diretórios',
    'requirements_header_system' => 'Sistema',
    'requirements_files_info' => 'Os ficheiros e diretórios mencionados em seguida devem estar disponíveis e dispor de direitos de escrita',
    'requirements_tablefiles_colcheck' => 'Ficheiro/diretório',
    'requirements_tablefiles_colstatus' => 'Estado',
    'requirements_error' => '<h3 class="alert-heading">Atenção!</h3>Não estão satisfeitos todos os requisitos necessários para uma instalação correta',
    'requirements_success' => '<h3 class="alert-heading">Parabéns!</h3>Estão satisfeitos todos os requisitos para uma instalação correta',
    'requirements_php_info' => 'O seu servidor deve satisfazer os seguintes requisitos de sistema para que possa executar o Shopware',
    'requirements_php_max_compatible_version' => 'Esta versão do Shopware suporta PHP até a versão %s. A funcionalidade completa com versões mais recentes do PHP não pode ser garantida.',
    'requirements_system_colcheck' => 'Requisitos',
    'requirements_system_colrequired' => 'Necessário',
    'requirements_system_colfound' => 'O seu sistema',
    'requirements_system_colstatus' => 'Estado',
    'requirements_show_all' => '(mostrar tudo)',
    'requirements_hide_all' => '(ocultar tudo)',

    'license_agreement_header' => 'Termos e Condições',
    'license_agreement_info' => 'Encontra aqui uma listagem dos termos e condições, que deverá ler atentamente e aceitar para que a instalação possa ser concluída. A Shopware Community Edition está licenciada de acordo com AGPL e parte dos plugins e o modelo estão licenciados de acordo com a licença New BSD.',
    'license_agreement_error' => 'É necessário que aceite os nossos termos e condições',
    'license_agreement_checkbox' => 'Concordo com os termos e condições',

    'database-configuration_header' => 'Configurar a base de dados',
    'database-configuration_field_host' => 'Servidor da base de dados:',
    'database-configuration_advanced_settings' => 'Apresentar configurações avançadas',
    'database-configuration_field_port' => 'Porta da base de dados:',
    'database-configuration_field_socket' => 'Socket da base de dados (opcional):',
    'database-configuration_field_user' => 'Utilizador da base de dados:',
    'database-configuration_field_password' => 'Palavra-passe da base de dados:',
    'database-configuration_field_database' => 'Nome da base de dados:',
    'database-configuration_field_new_database' => 'New database:',
    'database-configuration_info' => 'Para instalar o Shopware no seu sistema são necessários os dados de acesso à base de dados. Caso não tenha a certeza dos dados que deve introduzir, contacte o seu administrador/anfitrião.',
    'database-configuration-create_new_database' => 'Criar uma nova base de dados',

    'database-import_header' => 'Instalação',
    'database-import_skip_import' => 'Ignorar',
    'database-import_progress' => 'Progresso: ',
    'database-import-hint' => '<strong>Nota: </strong> caso a base de dados configurada já contenha tabelas Shopware, estas são eliminadas com a instalação/atualização!',
    'migration_counter_text_migrations' => 'A atualizar a base de dados',
    'migration_counter_text_snippets' => 'A atualizar os fragmentos de texto',
    'migration_update_success' => 'Base de dados importada com êxito!',

    'edition_header' => 'Já adquiriu a sua licença Shopware?',
    'edition_info' => 'O Shopware está disponível na versão gratuita <a href="https://en.shopware.com/pricing/" target="_blank">Community Edition </a> e nas <a href="https://en.shopware.com/pricing/" target="_blank">versões pagas Professional, Professional Plus e Enterprise</a>.',
    'edition_ce' => 'Não, pretendo utilizar a versão gratuita <a href="https://en.shopware.com/pricing/" target="_blank">Community Edition</a>.',
    'edition_cm' => 'Sim, tenho uma licença paga Shopware (<a href="https://en.shopware.com/pricing/" target="_blank">Professional, Professional Plus ou Enterprise</a>).',
    'edition_license' => 'Introduza aqui o código da sua licença. Este consta da sua conta Shopware, em "Licenças" &rarr; "Licenças de produtos" &rarr; "Detalhes/Descarregar":',
    'edition_license_error' => 'É necessária uma licença válida para a instalação de uma versão paga do Shopware.',

    'configuration_header' => 'Configurações iniciais da loja',
    'configuration_sconfig_text' => 'Falta pouco! Agora é necessário definir a configuração inicial da sua loja para concluir a instalação. Tudo o que for definido pode, obviamente, ser modificado posteriormente!',
    'configuration_sconfig_name' => 'Nome da sua loja:',
    'configuration_sconfig_name_info' => 'Introduza o nome da sua loja',
    'configuration_sconfig_mail' => 'Endereço de correio eletrónico da sua loja:',
    'configuration_sconfig_mail_info' => 'Indique o endereço de correio eletrónico para envio de correio eletrónico',
    'configuration_sconfig_domain' => 'Domínio da loja:',
    'configuration_sconfig_language' => 'Idioma principal:',
    'configuration_sconfig_currency' => 'Moeda predefinida:',
    'configuration_sconfig_currency_info' => 'Esta moeda é utilizada por defeito ao definir o preço do artigo',
    'configuration_admin_currency_eur' => 'Euro',
    'configuration_admin_currency_usd' => 'Dólar americano (US)',
    'configuration_admin_currency_gbp' => 'Libra inglesa (GB)',
    'configuration_admin_username' => 'Nome de início de sessão do admin:',
    'configuration_admin_mail' => 'Endereço de correio eletrónico do admin:',
    'configuration_admin_firstName' => 'first name:',
    'configuration_admin_lastName' => 'last name:',

    'configuration_admin_language_de' => 'Alemão',
    'configuration_admin_language_en' => 'Inglês',
    'configuration_admin_password' => 'Palavra passe do admin:',

    'finish_header' => 'Instalação concluída',
    'finish_info' => 'Instalou o Shopware com êxito!',
    'finish_info_heading' => 'Parabéns!',
    'finish_first_steps' => 'Guia "Passos iniciais"',
    'finish_frontend' => 'Ir para front-end da loja',
    'finish_backend' => 'Ir para back-end da loja (administração)',
    'finish_message' => '
<p>
    <strong>Seja bem vindo ao Shopware,</strong>
</p>
<p>
    Ficamos muitos satisfeitos em recebê-lo na nossa comunidade. Instalou o Shopware com êxito.
<p>A sua loja está operacional. Caso esteja agora a começar a utilizar o Shopware, recomendamos a leitura do guia <a href="https://docs.shopware.com/en/shopware-5-en/first-steps/first-steps-in-shopware" target="_blank">"Passos iniciais no Shopware"</a>. Caso esteja a entrar pela primeira vez no back-end da loja, terá a ajuda do nosso assistente de primeira execução para o guiar durante o processo.</p>
<p>Esperamos que se divirta na nossa nova loja online!</p>',
];
