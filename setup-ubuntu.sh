#!/bin/bash
set -e

# ==============================================================================
# Skrypt instalacji i konfiguracji INVENTARYZATOR na serwerze Ubuntu/Debian + Nginx
# ==============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Uruchom ten skrypt z uprawnieniami roota (sudo ./setup-ubuntu.sh)"
  exit 1
fi

APP_DIR="/var/www/inventaryzator"
CURRENT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null 2>&1 && pwd)"

echo "=== 1. Aktualizacja pakietów i instalacja Nginx oraz PHP-FPM ==="
apt update
apt install -y nginx php-fpm php-sqlite3 php-mbstring php-xml php-curl sqlite3

# Wykrycie wersji PHP-FPM zainstalowanej w systemie
PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" 2>/dev/null | sort -V | tail -n 1)

if [ -z "$PHP_SOCK" ]; then
    # Jeśli serwis fpm jeszcze nie wygenerował socketu, spróbuj uruchomić
    systemctl restart php*-fpm 2>/dev/null || true
    PHP_SOCK=$(find /run/php/ -name "php*-fpm.sock" 2>/dev/null | sort -V | tail -n 1)
fi

echo "Wykryty socket PHP-FPM: ${PHP_SOCK:-nie znaleziono (użyto domyślnego)}"

echo "=== 2. Kopiowanie plików aplikacji do ${APP_DIR} ==="
if [ "$CURRENT_DIR" != "$APP_DIR" ]; then
    mkdir -p "$APP_DIR"
    cp -r "$CURRENT_DIR"/* "$APP_DIR"/
    # Skopiuj również ukryte pliki (oprócz .git jeśli niepotrzebny)
    cp -r "$CURRENT_DIR"/.[!.]* "$APP_DIR"/ 2>/dev/null || true
fi

echo "=== 3. Ustawianie uprawnień do katalogów i bazy SQLite ==="
# Katalog data/ oraz plik bazy SQLite MUSZĄ mieć uprawnienia zapisu dla www-data
mkdir -p "$APP_DIR/data"
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;

# Katalog data/ wymaga 775, aby SQLite mógł tworzyć pliki tymczasowe (WAL / journal)
chmod -R 775 "$APP_DIR/data"
if [ -f "$APP_DIR/data/inventory.sqlite" ]; then
    chmod 664 "$APP_DIR/data/inventory.sqlite"
fi

echo "=== 4. Konfiguracja Nginx ==="
NGINX_TARGET="/etc/nginx/sites-available/inventaryzator"
cp "$APP_DIR/nginx.conf" "$NGINX_TARGET"

# Jeśli znaleziono inny socket php, zaktualizuj w konfiguracji Nginx
if [ -n "$PHP_SOCK" ]; then
    sed -i "s|fastcgi_pass unix:.*php.*\.sock;|fastcgi_pass unix:${PHP_SOCK};|g" "$NGINX_TARGET"
fi

# Aktywacja vhosta
ln -sf "$NGINX_TARGET" /etc/nginx/sites-enabled/inventaryzator

# Wyłączenie domyślnego vhosta nginx (opcjonalnie, jeśli zajmuje port 80)
if [ -f /etc/nginx/sites-enabled/default ]; then
    rm -f /etc/nginx/sites-enabled/default
fi

echo "=== 5. Test konfiguracji i przeładowanie Nginx ==="
nginx -t

systemctl enable nginx
systemctl restart nginx

echo ""
echo "=========================================================="
echo " Instalacja zakonczona sukcesem!"
echo " Aplikacja dziala pod adresem IP / domena Twojego serwera."
echo " Sciezka projektu: $APP_DIR"
echo "=========================================================="

