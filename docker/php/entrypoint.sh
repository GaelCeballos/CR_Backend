#!/bin/sh
set -e

# Sin argumentos (Render, "docker compose up app"): arranca el servidor normal.
# Con argumentos ("docker compose run app_test php artisan test"): los respeta
# tal cual, igual que si no hubiera ENTRYPOINT, para no romper ese flujo.
if [ "$#" -gt 0 ]; then
    exec "$@"
fi

php artisan migrate --force

# config:cache/route:cache cachean las variables de entorno dentro del
# contenedor: en local eso esconde los cambios a .env hasta un rebuild, asi
# que solo se cachea en producción (Render inyecta sus variables una sola vez
# al arrancar, no cambian entre peticiones).
if [ "$APP_ENV" = "production" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

exec apache2-foreground
