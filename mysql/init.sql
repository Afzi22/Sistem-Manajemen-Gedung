-- Memastikan basis data ada
CREATE DATABASE IF NOT EXISTS ${DB_NAME}
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE ${DB_NAME};

-- Tabel Supplier (Pemasok)
CREATE TABLE IF NOT EXISTS Suppliers (
    SupplierID INT AUTO_INCREMENT PRIMARY KEY,
    SupplierName VARCHAR(255) NOT NULL,
    Phone VARCHAR(20),
    Address TEXT
) ENGINE=InnoDB;

-- Tabel Items (Barang Gudang)
CREATE TABLE IF NOT EXISTS Items (
    ItemID INT AUTO_INCREMENT PRIMARY KEY,
    SKU VARCHAR(50) UNIQUE NOT NULL,
    ItemName VARCHAR(255) NOT NULL,
    Category VARCHAR(100),
    Stock INT NOT NULL DEFAULT 0,
    Price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    CHECK (Stock >= 0)
) ENGINE=InnoDB;

-- Tabel StockTransactions (Catatan Barang Masuk/Keluar)
CREATE TABLE IF NOT EXISTS StockTransactions (
    TransactionID INT AUTO_INCREMENT PRIMARY KEY,
    ItemID INT NOT NULL,
    SupplierID INT,
    TransactionType ENUM('IN', 'OUT') NOT NULL,
    Quantity INT NOT NULL,
    TransactionDate DATE NOT NULL,
    FOREIGN KEY (ItemID) REFERENCES Items(ItemID),
    FOREIGN KEY (SupplierID) REFERENCES Suppliers(SupplierID)
) ENGINE=InnoDB;

-- Data Contoh (Dummy Data)
INSERT INTO Suppliers (SupplierName, Phone, Address) VALUES
('PT Sumber Makmur', '081234567890', 'Jl. Industri No. 10, Bandung'),
('CV Berkah Jaya', '089876543210', 'Jl. Gudang Selatan No. 5, Cimahi');

INSERT INTO Items (SKU, ItemName, Category, Stock, Price) VALUES
('BRG-001', 'Kardus Box Besar', 'Kemasan', 150, 5000.00),
('BRG-002', 'Plastik Bubble Wrap', 'Kemasan', 75, 50000.00),
('BRG-003', 'Lakban Cokelat', 'Alat Packing', 200, 12000.00);

INSERT INTO StockTransactions (ItemID, SupplierID, TransactionType, Quantity, TransactionDate) VALUES
(1, 1, 'IN', 150, '2026-10-01'),
(2, 2, 'IN', 75, '2026-10-02'),
(3, 1, 'IN', 200, '2026-10-03');