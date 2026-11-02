# inwentaryzator-php-sqlite

Mobilna aplikacja do inwentaryzacji sprzętu komputerowego oparta o PHP oraz SQLite, stylizowana czystym Bootstrap 5 (mobile-first).

## Funkcjonalności

- **Zoptymalizowany interfejs mobilny**: Prosty widok formularza oraz kart na telefonie (lub tabeli na komputerze).
- **Dynamiczne słowniki**: Wybór typu urządzenia oraz producenta z bazy danych z możliwością natychmiastowego wpisania własnej wartości ("Inny..."). Nowo dodane wartości automatycznie zapisują się w bazie i są dostępne na liście przy kolejnym dodawaniu sprzętu.
- **Wyszukiwanie i filtrowanie**: Szybkie wyszukiwanie po S/N, numerze inwentarzowym, pokoju lub uwagach oraz filtrowanie po pokojach, typach i producentach.
- **Eksport do CRM**: Jednoklikowy eksport aktualnie przefiltrowanej listy inwentaryzacyjnej do pliku CSV (zoptymalizowanego pod polskie kodowanie w Excelu z BOM i separatorem średnika).
- **Prosta autoryzacja**: System logowania użytkowników (Admin / User).

## Wymagania

- PHP 8.0+
- Aktywne rozszerzenia: `pdo_sqlite`, `sqlite3`, `mbstring`
- Serwer HTTP (Nginx / Apache / wbudowany serwer PHP)

---

## Wdrożenie na Ubuntu + Nginx

### Opcja A: Automatyczna instalacja skryptem
1. Skopiuj repozytorium na serwer Ubuntu (np. do `/var/www/inventaryzator`).
2. Uruchom skrypt instalacyjny:
   ```bash
   sudo chmod +x setup-ubuntu.sh
   sudo ./setup-ubuntu.sh
   ```

### Opcja B: Ręczna konfiguracja krok po kroku

1. **Instalacja pakietów**:
   ```bash
   sudo apt update
   sudo apt install -y nginx php-fpm php-sqlite3 php-mbstring php-xml sqlite3
   ```

2. **Kopiowanie plików do katalogu docelowego**:
   ```bash
   sudo mkdir -p /var/www/inventaryzator
   sudo cp -r . /var/www/inventaryzator/
   ```

3. **Ustawienie uprawnień do bazy SQLite**:
   SQLite wymaga praw zapisu nie tylko do pliku bazy, ale **również do katalogu `data/`** (w celu tworzenia plików dziennika WAL / journal):
   ```bash
   sudo chown -R www-data:www-data /var/www/inventaryzator
   sudo chmod -R 755 /var/www/inventaryzator
   sudo chmod -R 775 /var/www/inventaryzator/data
   ```

4. **Konfiguracja vhosta w Nginx**:
   Skopiuj dostarczony plik [nginx.conf](file:///nginx.conf):
   ```bash
   sudo cp /var/www/inventaryzator/nginx.conf /etc/nginx/sites-available/inventaryzator
   sudo ln -s /etc/nginx/sites-available/inventaryzator /etc/nginx/sites-enabled/
   ```
   *(Upewnij się, że socket `fastcgi_pass` w konfiguracji odpowiada Twojej wersji PHP, np. `/run/php/php8.1-fpm.sock` na Ubuntu 22.04 lub `php8.3-fpm.sock` na Ubuntu 24.04).*

5. **Test i restart serwera**:
   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

> [!IMPORTANT]
> Plik [nginx.conf](file:///nginx.conf) zawiera reguły blokujące bezpośredni dostęp do bazy SQLite (`data/inventory.sqlite`) oraz plików konfiguracyjnych przez przeglądarkę WWW.

---

## Uruchomienie lokalne (Development)

1. Uruchom serwer PHP w głównym katalogu projektu:
   ```bash
   php -d extension=pdo_sqlite -d extension=sqlite3 -S localhost:8000
   ```
2. Otwórz w przeglądarce adres `http://localhost:8000/`.
3. System automatycznie utworzy bazę danych w katalogu `data/inventory.sqlite` i zasili słowniki domyślnymi danymi.

## Logowanie

Domyślne konto administratora (utworzone automatycznie przy instalacji):
- **Login**: `admin`
- **Hasło**: `CHANGE_ME_STRONG_PASSWORD`
