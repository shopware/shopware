#!/usr/bin/env bash

load_dotenv() {
    LOAD_DOTENV=${LOAD_DOTENV:-"1"}

    if [[ "$LOAD_DOTENV" == "0" ]]; then
        return
    fi

    CURRENT_ENV=${APP_ENV:-"dev"}
    env_file="$1"

    # If we have an actual .env file load it
    if [[ -e "$env_file" ]]; then
        # shellcheck source=/dev/null
        source "$env_file"
    elif [[ -e "$env_file.dist" ]]; then
        # shellcheck source=/dev/null
        source "$env_file.dist"
    fi

    # If we have an local env file load it
    if [[ -e "$env_file.local" ]]; then
        # shellcheck source=/dev/null
        source "$env_file.local"
    fi

    # If we have an env file for the current env load it
    if [[ -e "$env_file.$CURRENT_ENV" ]]; then
        # shellcheck source=/dev/null
        source "$env_file.$CURRENT_ENV"
    fi

    # If we have an env file for the current env load it'
    if [[ -e "$env_file.$CURRENT_ENV.local" ]]; then
        # shellcheck source=/dev/null
        source "$env_file.$CURRENT_ENV.local"
    fi
}
