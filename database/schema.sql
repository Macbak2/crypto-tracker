-- ============================================
-- CRYPTO TRACKER - DATABASE SCHEMA
-- ============================================
-- Baza danych dla systemu śledzenia operacji kryptowalutowych
-- Giełda: Zonda (BitBay)
-- ============================================

CREATE DATABASE IF NOT EXISTS crypto_tracker_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crypto_tracker_db;

-- ============================================
-- TABELA: transactions
-- Główna tabela z transakcjami handlowymi z pliku "Transakcje"
-- ============================================
CREATE TABLE IF NOT EXISTS transactions (
    id VARCHAR(36) PRIMARY KEY COMMENT 'UUID z pliku CSV',
    market VARCHAR(20) NOT NULL COMMENT 'Para handlowa np. BCC-PLN',
    datetime DATETIME NOT NULL COMMENT 'Data i czas transakcji',
    type ENUM('buy', 'sell') NOT NULL COMMENT 'Rodzaj: kupno lub sprzedaż',
    order_type ENUM('maker', 'taker') NOT NULL COMMENT 'Typ zlecenia',
    rate DECIMAL(20, 8) NOT NULL COMMENT 'Kurs jednostkowy',
    amount DECIMAL(20, 8) NOT NULL COMMENT 'Ilość kryptowaluty',
    value DECIMAL(20, 2) NOT NULL COMMENT 'Wartość w PLN',
    notes TEXT NULL COMMENT 'Notatki użytkownika',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_datetime (datetime),
    INDEX idx_market (market),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: operations
-- Szczegółowe operacje z pliku "Szczegółowy raport"
-- ============================================
CREATE TABLE IF NOT EXISTS operations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    datetime DATETIME NOT NULL COMMENT 'Data i czas operacji',
    operation_type VARCHAR(100) NOT NULL COMMENT 'Typ operacji',
    amount DECIMAL(20, 8) NOT NULL COMMENT 'Kwota (może być ujemna)',
    currency VARCHAR(10) NOT NULL COMMENT 'Waluta',
    balance_available DECIMAL(20, 8) DEFAULT 0 COMMENT 'Saldo dostępne po operacji',
    balance_locked DECIMAL(20, 8) DEFAULT 0 COMMENT 'Saldo zablokowane po operacji',
    balance_total DECIMAL(20, 8) DEFAULT 0 COMMENT 'Saldo całkowite po operacji',
    notes TEXT NULL COMMENT 'Notatki użytkownika',
    transaction_id VARCHAR(36) NULL COMMENT 'Powiązanie z transactions (jeśli możliwe)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_datetime (datetime),
    INDEX idx_currency (currency),
    INDEX idx_operation_type (operation_type),
    INDEX idx_transaction_id (transaction_id),
    
    FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: import_history
-- Historia importów plików CSV
-- ============================================
CREATE TABLE IF NOT EXISTS import_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL COMMENT 'Nazwa pliku',
    file_type ENUM('transactions', 'operations', 'transfers', 'detailed_report') NOT NULL COMMENT 'Typ pliku',
    records_count INT NOT NULL COMMENT 'Ilość zaimportowanych rekordów',
    date_from DATE NULL COMMENT 'Zakres danych: od',
    date_to DATE NULL COMMENT 'Zakres danych: do',
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Kiedy zaimportowano',
    status ENUM('success', 'partial', 'failed') DEFAULT 'success' COMMENT 'Status importu',
    notes TEXT NULL COMMENT 'Dodatkowe informacje/błędy',
    
    INDEX idx_imported_at (imported_at),
    INDEX idx_file_type (file_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TABELA: currencies
-- Słownik kryptowalut (opcjonalne, dla przyszłych funkcji)
-- ============================================
CREATE TABLE IF NOT EXISTS currencies (
    code VARCHAR(10) PRIMARY KEY COMMENT 'Kod waluty np. BTC, ETH',
    name VARCHAR(100) NOT NULL COMMENT 'Pełna nazwa',
    type ENUM('crypto', 'fiat') DEFAULT 'crypto' COMMENT 'Typ waluty',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Czy aktywna',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- WIDOKI (VIEWS) - dla łatwiejszych zapytań
-- ============================================

-- Widok: Podsumowanie transakcji z prowizjami
CREATE OR REPLACE VIEW v_transactions_with_fees AS
SELECT 
    t.id,
    t.market,
    t.datetime,
    t.type,
    t.order_type,
    t.rate,
    t.amount,
    t.value,
    COALESCE(SUM(ABS(o.amount)), 0) as total_fee,
    o.currency as fee_currency
FROM transactions t
LEFT JOIN operations o ON t.id = o.transaction_id 
    AND o.operation_type LIKE '%prowizji%'
GROUP BY t.id, o.currency;

-- Widok: Saldo per waluta (ostatnie znane)
CREATE OR REPLACE VIEW v_latest_balances AS
SELECT 
    currency,
    balance_available,
    balance_locked,
    balance_total,
    datetime,
    ROW_NUMBER() OVER (PARTITION BY currency ORDER BY datetime DESC) as rn
FROM operations
WHERE balance_total IS NOT NULL;

-- ============================================
-- DANE TESTOWE - podstawowe waluty
-- ============================================
INSERT INTO currencies (code, name, type) VALUES
    ('PLN', 'Polski złoty', 'fiat'),
    ('BTC', 'Bitcoin', 'crypto'),
    ('ETH', 'Ethereum', 'crypto'),
    ('LTC', 'Litecoin', 'crypto'),
    ('BCC', 'Bitcoin Cash', 'crypto'),
    ('BCH', 'Bitcoin Cash', 'crypto'),
    ('LSK', 'Lisk', 'crypto'),
    ('DASH', 'Dash', 'crypto'),
    ('GAME', 'GameCredits', 'crypto')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================
-- KONIEC SCHEMATU
-- ============================================
