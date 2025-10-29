#!/usr/bin/env bash
set -euo pipefail

APP_ROOT="/var/www"
STORAGE_DIR="${APP_ROOT}/storage"
STORAGE_SKELETON="/opt/app/storage-skeleton"

mkdir -p \
  "${STORAGE_DIR}/app/public" \
  "${STORAGE_DIR}/framework/cache" \
  "${STORAGE_DIR}/framework/sessions" \
  "${STORAGE_DIR}/framework/testing" \
  "${STORAGE_DIR}/framework/views" \
  "${STORAGE_DIR}/logs"

if [ -d "${STORAGE_SKELETON}" ]; then
  if [ ! -f "${STORAGE_DIR}/mock-services.json" ] && [ -f "${STORAGE_SKELETON}/mock-services.json" ]; then
    cp "${STORAGE_SKELETON}/mock-services.json" "${STORAGE_DIR}/"
  fi

  if [ -d "${STORAGE_SKELETON}/framework" ]; then
    for path in cache sessions testing views; do
      if [ -d "${STORAGE_SKELETON}/framework/${path}" ]; then
        rsync -a "${STORAGE_SKELETON}/framework/${path}/" "${STORAGE_DIR}/framework/${path}/" >/dev/null 2>&1 || true
      fi
    done
  fi
fi

ln -sfn ../storage/app/public "${APP_ROOT}/public/storage"

touch "${STORAGE_DIR}/logs/laravel.log"
chmod -R ug+rwX "${STORAGE_DIR}" "${APP_ROOT}/bootstrap/cache"
chown -R www-data:www-data "${STORAGE_DIR}" "${APP_ROOT}/bootstrap/cache"

exec "$@"
