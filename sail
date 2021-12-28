#!/usr/bin/env bash

# From Laravel Sail

# Define environment variables...
export APP_PORT=${APP_PORT:-88}
export APP_SERVICE=${APP_SERVICE:-api}
export DB_PORT=${DB_PORT:-3308}
export WWWUSER=${WWWUSER:-$UID}
export WWWGROUP=${WWWGROUP:-$(id -g)}

if [ $# -gt 0 ]; then

    # START
    if [ "$1" == "start" ]; then
        docker-compose up -d
    # STOP
    elif [ "$1" == "stop" ]; then
        docker-compose stop
    # DESTROY
    elif [ "$1" == "destroy" ]; then
        docker-compose down

    # Proxy PHP commands to the "php" binary on the application container...
    elif [ "$1" == "php" ]; then
        shift 1

        docker-compose exec \
            -u sail \
            "$APP_SERVICE" \
            php "$@"

    # Proxy Artisan commands to the "artisan" binary on the application container...
    elif [ "$1" == "artisan" ] || [ "$1" == "art" ]; then
        shift 1

        docker-compose exec \
            -u sail \
            "$APP_SERVICE" \
            php artisan "$@"


    # Initiate a MySQL CLI terminal session within the "mysql" container...
    elif [ "$1" == "mysql" ]; then
        shift 1

        docker-compose exec \
            mariadb \
            bash -c 'MYSQL_PWD=${MYSQL_PASSWORD} mysql -u ${MYSQL_USER} ${MYSQL_DATABASE}'
    fi

else
    docker-compose ps -a
fi
