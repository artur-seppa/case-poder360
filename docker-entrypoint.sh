#!/bin/sh
set -e

if ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
fi

touch database/database.sqlite

php artisan migrate --force
php artisan db:seed --force

php artisan scribe:generate || echo "Aviso: falha ao gerar a documentação do Scribe, seguindo sem ela."

exec php artisan serve --host=0.0.0.0 --port=8000
