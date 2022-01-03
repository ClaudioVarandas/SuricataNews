#!/usr/bin/env bash

# From Laravel Sail

# Define environment variables...
export APP_PORT=${APP_PORT:-88}
export APP_SERVICE=${APP_SERVICE:-api}
export DB_PORT=${DB_PORT:-3308}
export WWWUSER=${WWWUSER:-$UID}
export WWWGROUP=${WWWGROUP:-$(id -g)}

# Ensure that Docker is running...
if ! docker info > /dev/null 2>&1; then
    echo -e "${WHITE}Docker is not running.${NC}" >&2

    exit 1
fi

# Determine if Sail is currently up...
PSRESULT="$(docker-compose ps -q)"

if docker-compose ps | grep 'Exit' &> /dev/null; then
    docker-compose down > /dev/null 2>&1

    EXEC="no"
elif [ -n "$PSRESULT" ]; then
    EXEC="yes"
else
    EXEC="no"
fi

# Function that outputs Sail is not running...
function sail_is_not_running {
    echo -e "${WHITE}Sail is not running.${NC}" >&2
    echo "" >&2
    echo -e "${WHITE}You may Sail using the following commands:${NC} './vendor/bin/sail up' or './vendor/bin/sail up -d'" >&2

    exit 1
}

if [ $# -gt 0 ]; then

    # START
    if [ "$1" == "start" ]; then
        shift 1

        docker-compose up -d "$@"
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

    # Initiate a Bash shell within the application container...
    elif [ "$1" == "shell" ] || [ "$1" == "bash" ]; then
        shift 1

        if [ "$EXEC" == "yes" ]; then
            docker-compose exec \
                -u sail \
                "$APP_SERVICE" \
                bash
        else
            sail_is_not_running
        fi

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
