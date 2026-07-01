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
- Aktywne rozszerzenia: `pdo_sqlite` oraz `sqlite3`

## Uruchomienie lokalne

1. Pobierz repozytorium.
2. Uruchom serwer PHP w głównym katalogu projektu:
   ```bash
   php -d extension=pdo_sqlite -d extension=sqlite3 -S localhost:8000
   ```
3. Otwórz w przeglądarce adres `http://localhost:8000/`.
4. System automatycznie utworzy bazę danych w katalogu `data/inventory.sqlite` i zasili słowniki domyślnymi danymi.

## Logowanie

Domyślne konto administratora (utworzone automatycznie przy instalacji):
- **Login**: `admin`
- **Hasło**: `CHANGE_ME_STRONG_PASSWORD`
