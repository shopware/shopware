# verbose
set -v

STARTPARAMS=""
DB=$1

if [[ $DB == "mysql:8.0.19" ]]; then
    STARTPARAMS="mysqld --default-authentication-plugin=mysql_native_password"
fi

docker run -it --mount type=tmpfs,destination=/var/lib/mysql --name=mysqld -d -e MYSQL_ROOT_PASSWORD=shopware -e MYSQL_DATABASE=shopware -p3306:3306 ${DB} ${STARTPARAMS}
sleep 5

mysql() {
    docker exec mysqld mysql "${@}"
}
while :
do
    sleep 5
    mysql -pshopware -e 'select version()'
    if [ $? = 0 ]; then
        break
    fi
    echo "server logs"
    docker logs --tail 5 mysqld
done

mysql -pshopware -e 'select VERSION()'
