{ lib, pkgs, config, ... }:

{
  enterShell = ''
    rm -f .devenv/bin
    ln -sf ${pkgs.buildEnv { name = "devenv"; paths = config.packages; ignoreCollisions = true; }}/bin .devenv/bin
  '';

  languages.php.enable = true;
  languages.php.package = pkgs.php.buildEnv {
    extraConfig = ''
      pdo_mysql.default_socket=''${MYSQL_UNIX_PORT}
      mysqli.default_socket=''${MYSQL_UNIX_PORT}
      memory_limit = 2G
    '';
  };

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

  services.caddy.enable = true;
  services.caddy.virtualHosts."http://localhost:8000" = {
    extraConfig = ''
      root * shop/public
      php_fastcgi unix/${config.languages.php.fpm.pools.web.socket}
      file_server
    '';
  };

  services.mysql.enable = true;
  services.mysql.initialDatabases = [{ name = "shopware"; }];
  services.mysql.ensureUsers = [
    {
      name = "shopware";
      password = "shopware";
      ensurePermissions = { "shopware.*" = "ALL PRIVILEGES"; };
    }
  ];

  scripts.build-phar.exec = ''
    composer run build-phar
    mkdir -p shop/public
    mv shopware-recovery.phar shop/public/shopware-recovery.phar.php
  '';

  processes.watch-phar.exec = ''
    ${pkgs.watchexec}/bin/watchexec -e php,js,yml,twig,css build-phar
  '';

  services.adminer.enable = true;
  services.adminer.listen = "127.0.0.1:8081";

  services.caddy.virtualHosts."http://localhost:3500" = {
    extraConfig = ''
      root * .
      file_server
    '';
  };

  services.wiremock = {
    enable = builtins.pathExists ./update.zip;
    mappings = [
      {
        request = {
          method = "GET";
          url = "/v1/release/update?shopware_version=6.4.17.2&channel=stable&major=6&code=";
        };
        response = {
          status = 200;
          jsonBody = {
            version = "6.4.18.0";
            release_date = false;
            security_update = false;
            uri = "http://localhost:3500/update.zip";
            size = lib.toInt (lib.fileContents (pkgs.runCommand "update.zip" { } ''
              du -b ${./update.zip} | cut -f1 > $out;
            ''));
            sha1 = builtins.hashFile "sha1" ./update.zip;
            sha256 = builtins.hashFile "sha256" ./update.zip;
            checks = [ ];
            changelog = {
              de = {
                language = "de";
                changelog = "German";
              };
              en = {
                language = "en";
                changelog = "English";
              };
            };
            isNewer = true;
          };
        };
      }
    ];
  };
}
