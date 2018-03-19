FROM mariadb:5.7

# copy mysql config
ADD dev.cnf /etc/mysql/conf.d/dev.cnf
ADD remote-access.cnf /etc/mysql/conf.d/remote-access.cnf

COPY grant.sql /docker-entrypoint-initdb.d/grant.sql
