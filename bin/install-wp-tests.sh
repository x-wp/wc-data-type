#!/usr/bin/env bash

set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CACHE_DIR="${REPO_ROOT}/.cache"
WP_CORE_DIR="${WP_CORE_DIR:-${CACHE_DIR}/wp-tests/wordpress}"
WP_TESTS_DIR="${WP_TESTS_DIR:-${CACHE_DIR}/wp-tests/lib}"
WP_DOWNLOADS_DIR="${CACHE_DIR}/wp-tests/downloads"
WP_DEVELOP_DIR="${CACHE_DIR}/wp-tests/wordpress-develop"
PLUGIN_SLUG="xwc-data-type-tests"
PLUGIN_SOURCE_DIR="${REPO_ROOT}/tests/wp-plugin/${PLUGIN_SLUG}"
PLUGIN_TARGET_DIR="${WP_CORE_DIR}/wp-content/plugins/${PLUGIN_SLUG}"

DB_NAME="${DB_NAME:-wordpress_test}"
DB_USER="${DB_USER:-wordpress}"
DB_PASSWORD="${DB_PASSWORD:-wordpress}"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-33067}"
DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-root}"

WP_VERSION="${WP_VERSION:-latest}"
WC_VERSION="${WC_VERSION:-latest}"

log() {
  printf '[xwc-tests] %s\n' "$1" >&2
}

wp_cli() {
  php -d memory_limit=512M "$(command -v wp)" "$@"
}

require_command() {
  if ! command -v "$1" >/dev/null 2>&1; then
    printf 'Missing required command: %s\n' "$1" >&2
    exit 1
  fi
}

wait_for_database() {
  log "Waiting for MySQL at ${DB_HOST}:${DB_PORT}"

  for _ in $(seq 1 30); do
    if mysqladmin ping \
      --protocol=tcp \
      --host="${DB_HOST}" \
      --port="${DB_PORT}" \
      --user="${DB_ROOT_USER}" \
      --password="${DB_ROOT_PASSWORD}" \
      --silent >/dev/null 2>&1; then
      return 0
    fi

    sleep 2
  done

  printf 'Timed out waiting for MySQL.\n' >&2
  exit 1
}

ensure_database() {
  mysql \
    --protocol=tcp \
    --host="${DB_HOST}" \
    --port="${DB_PORT}" \
    --user="${DB_ROOT_USER}" \
    --password="${DB_ROOT_PASSWORD}" \
    --execute="CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\`;"
}

ensure_wordpress() {
  mkdir -p "${WP_CORE_DIR}" "${WP_DOWNLOADS_DIR}"

  if [ ! -f "${WP_CORE_DIR}/wp-load.php" ]; then
    log "Downloading WordPress ${WP_VERSION}"
    wp_cli core download --version="${WP_VERSION}" --path="${WP_CORE_DIR}" --force
  fi

  if [ ! -f "${WP_CORE_DIR}/wp-config.php" ]; then
    log 'Creating wp-config.php'
    wp_cli config create \
      --path="${WP_CORE_DIR}" \
      --dbname="${DB_NAME}" \
      --dbuser="${DB_USER}" \
      --dbpass="${DB_PASSWORD}" \
      --dbhost="${DB_HOST}:${DB_PORT}" \
      --skip-check \
      --force
  fi

  if ! wp_cli core is-installed --path="${WP_CORE_DIR}" >/dev/null 2>&1; then
    log 'Installing WordPress site'
    wp_cli core install \
      --path="${WP_CORE_DIR}" \
      --url="http://localhost:8080" \
      --title="XWC Data Type Tests" \
      --admin_user="admin" \
      --admin_password="password" \
      --admin_email="admin@example.org"
  fi
}

download_wordpress_develop() {
  local wp_core_version="$1"
  local archive_path="${WP_DOWNLOADS_DIR}/wordpress-develop-${wp_core_version}.zip"
  local extract_root="${WP_DOWNLOADS_DIR}/wordpress-develop-${wp_core_version}"
  local source_root

  if [ ! -d "${extract_root}" ]; then
    mkdir -p "${WP_DOWNLOADS_DIR}"

    if [ ! -f "${archive_path}" ]; then
      log "Downloading wordpress-develop ${wp_core_version}"
      if ! curl -fsSL -o "${archive_path}" "https://github.com/WordPress/wordpress-develop/archive/refs/tags/${wp_core_version}.zip"; then
        log "Falling back to trunk for wordpress-develop ${wp_core_version}"
        curl -fsSL -o "${archive_path}" "https://github.com/WordPress/wordpress-develop/archive/refs/heads/trunk.zip"
      fi
    fi

    rm -rf "${extract_root}"
    mkdir -p "${extract_root}"
    unzip -q -o "${archive_path}" -d "${extract_root}"
  fi

  source_root="$(find "${extract_root}" -mindepth 1 -maxdepth 1 -type d | head -n 1)"

  if [ -z "${source_root}" ]; then
    printf 'Unable to locate extracted wordpress-develop files.\n' >&2
    exit 1
  fi

  printf '%s\n' "${source_root}"
}

ensure_wordpress_tests_suite() {
  local wp_core_version source_root

  mkdir -p "${WP_TESTS_DIR}"

  if [ -f "${WP_TESTS_DIR}/includes/bootstrap.php" ] && [ -f "${WP_TESTS_DIR}/wp-tests-config.php" ]; then
    return 0
  fi

  wp_core_version="$(wp_cli core version --path="${WP_CORE_DIR}")"
  source_root="$(download_wordpress_develop "${wp_core_version}")"

  log "Preparing WordPress test suite for ${wp_core_version}"
  rm -rf "${WP_TESTS_DIR}"
  mkdir -p "${WP_TESTS_DIR}"
  cp -R "${source_root}/tests/phpunit/." "${WP_TESTS_DIR}/"

  cat > "${WP_TESTS_DIR}/wp-tests-config.php" <<PHP
<?php
define( 'DB_NAME', '${DB_NAME}' );
define( 'DB_USER', '${DB_USER}' );
define( 'DB_PASSWORD', '${DB_PASSWORD}' );
define( 'DB_HOST', '${DB_HOST}:${DB_PORT}' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'localhost:8080' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'XWC Data Type Test Site' );
define( 'WP_PHP_BINARY', 'php' );

define( 'ABSPATH', '${WP_CORE_DIR}/' );
define( 'WP_DEBUG', true );
define( 'FS_METHOD', 'direct' );

\$_SERVER['SERVER_NAME'] = 'localhost';
PHP
}

ensure_test_plugin() {
  mkdir -p "${WP_CORE_DIR}/wp-content/plugins"
  rm -rf "${PLUGIN_TARGET_DIR}"
  ln -s "${PLUGIN_SOURCE_DIR}" "${PLUGIN_TARGET_DIR}"
}

ensure_woocommerce() {
  log "Installing WooCommerce ${WC_VERSION}"

  if [ "${WC_VERSION}" = "latest" ]; then
    wp_cli plugin install woocommerce --force --activate --path="${WP_CORE_DIR}"
  else
    wp_cli plugin install woocommerce --version="${WC_VERSION}" --force --activate --path="${WP_CORE_DIR}"
  fi

  wp_cli plugin activate "${PLUGIN_SLUG}" --path="${WP_CORE_DIR}" >/dev/null 2>&1 || true
}

main() {
  require_command curl
  require_command docker
  require_command mysql
  require_command mysqladmin
  require_command php
  require_command unzip
  require_command wp

  export WP_CLI_CACHE_DIR="${CACHE_DIR}/wp-cli"

  wait_for_database
  ensure_database
  ensure_wordpress
  ensure_wordpress_tests_suite
  ensure_test_plugin
  ensure_woocommerce

  log 'WordPress test environment is ready'
}

main "$@"
