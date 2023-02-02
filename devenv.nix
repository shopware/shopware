{ pkgs, lib, config, ... }:

{
  packages = [
    pkgs.gnupatch
    pkgs.nodePackages_latest.yalc
  ];

  languages.javascript.enable = true;
  languages.javascript.package = lib.mkDefault pkgs.nodejs-18_x;

  languages.php.enable = true;
  languages.php.package = lib.mkDefault (pkgs.php.buildEnv {
    extensions = { all, enabled }: with all; enabled ++ [ amqp redis blackfire grpc ];
    extraConfig = ''
      memory_limit = 2G
      pdo_mysql.default_socket=''${MYSQL_UNIX_PORT}
      mysqli.default_socket=''${MYSQL_UNIX_PORT}
      blackfire.agent_socket = "${config.services.blackfire.socket}";
      realpath_cache_ttl=3600
      session.gc_probability=0
      session.save_handler = redis
      session.save_path = "tcp://127.0.0.1:6379/0"
      display_errors = On
      error_reporting = E_ALL
      assert.active=0
      opcache.memory_consumption=256M
      opcache.interned_strings_buffer=20
      zend.assertions = 0
      short_open_tag = 0
      zend.detect_unicode=0
      realpath_cache_ttl=3600
    '';
  });

  languages.php.fpm.pools.web = {
    settings = {
      "clear_env" = "no";
      "pm" = "dynamic";
      "pm.max_children" = 10;
      "pm.start_servers" = 2;
      "pm.min_spare_servers" = 1;
      "pm.max_spare_servers" = 10;
    };
  };

  services.caddy.enable = lib.mkDefault true;
  services.caddy.virtualHosts."http://localhost:8000" = {
    extraConfig = ''
      root * public
      php_fastcgi unix/${config.languages.php.fpm.pools.web.socket}
      file_server
    '';
  };

  services.mysql.enable = lib.mkDefault true;
  services.mysql.initialDatabases = [
    { name = "shopware"; }
  ];
  services.mysql.ensureUsers = [
    {
      name = "shopware";
      password = "shopware";
      ensurePermissions = {
        "shopware.*" = "ALL PRIVILEGES";
        "shopware_test.*" = "ALL PRIVILEGES";
      };
    }
  ];
  services.mysql.settings = {
    mysqld = {
      log_bin_trust_function_creators = 1;
    };
  };

  services.redis.enable = lib.mkDefault true;
  services.adminer.enable = lib.mkDefault true;
  services.adminer.listen = lib.mkDefault "127.0.0.1:9080";
  services.mailhog.enable = lib.mkDefault true;

  # services.elasticsearch.enable = true;
  # services.rabbitmq.enable = true;
  # services.rabbitmq.managementPlugin.enable = true;

  # Environment variables

  env.APP_URL = lib.mkDefault "http://localhost:8000";
  env.APP_SECRET = lib.mkDefault "devsecret";
  env.CYPRESS_baseUrl = lib.mkDefault "http://localhost:8000";
  env.DATABASE_URL = lib.mkDefault "mysql://root@localhost:3306/shopware";
  env.MAILER_DSN = lib.mkDefault "smtp://localhost:1025";
}
