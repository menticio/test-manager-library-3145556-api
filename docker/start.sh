#!/bin/sh
# Startup (T-17): migrations own the schema (ADR-001), then the admin comes from the environment (D-10), then the API serves.
set -e
php artisan migrate --force
php artisan library:provision-admin
exec php artisan serve --host=0.0.0.0 --port=8000
