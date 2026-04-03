# Crypto Tracker

System do zarządzania operacjami kryptowalutowymi z giełdy Zonda (dawniej BitBay).

## Funkcje

- Import plików CSV z Zondy (Transakcje, Operacje, Transfery, Szczegółowy raport)
- Historia transakcji z filtrowaniem i paginacją
- Ręczne dodawanie, edycja i usuwanie transakcji
- Notatki do transakcji (np. opłaty sieciowe, transfery między giełdami)
- Obliczanie zysków i strat metodą FIFO *(w planach)*
- Raport podatkowy PIT-38 *(w planach)*

## Stack technologiczny

- **Frontend:** React
- **Backend:** PHP (XAMPP)
- **Baza danych:** MySQL (`crypto_tracker_db`)

## Uruchomienie

1. Sklonuj repo do `C:\xampp\htdocs\Projekty-Github\crypto-tracker`
2. Uruchom XAMPP (Apache + MySQL)
3. Utwórz bazę danych — zaimportuj `database/schema.sql` przez phpMyAdmin
4. Zainstaluj zależności frontendu:
   ```bash
   cd frontend
   npm install
   npm start
   ```
5. Aplikacja dostępna pod `http://localhost:3000`
6. Backend API pod `http://localhost/Projekty-Github/crypto-tracker/backend/api`

## Struktura projektu

```
crypto-tracker/
├── backend/          # PHP API
│   ├── api/          # Endpointy REST
│   ├── config/       # Konfiguracja bazy
│   └── utils/        # Parser CSV
├── frontend/         # React
│   └── src/
│       ├── pages/
│       └── services/
└── database/         # Schema SQL
```

## Współpraca

Projekt tworzony we współpracy z Claude (Anthropic).
