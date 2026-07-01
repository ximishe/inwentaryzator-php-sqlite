-- Schema SQL for SQLite (Mobile Inventory System)

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('admin', 'user')),
  active INTEGER NOT NULL DEFAULT 1, -- 1 = Active, 0 = Inactive
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS manufacturers (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS device_types (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS inventory (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  room_number TEXT NOT NULL,
  device_type_id INTEGER DEFAULT NULL,
  manufacturer_id INTEGER DEFAULT NULL,
  serial_number TEXT NOT NULL,
  inventory_number TEXT NOT NULL,
  notes TEXT DEFAULT NULL,
  created_by INTEGER NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (device_type_id) REFERENCES device_types(id) ON DELETE SET NULL,
  FOREIGN KEY (manufacturer_id) REFERENCES manufacturers(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Index creation for optimization
CREATE INDEX IF NOT EXISTS idx_inventory_manufacturer_id ON inventory(manufacturer_id);
CREATE INDEX IF NOT EXISTS idx_inventory_device_type_id ON inventory(device_type_id);
CREATE INDEX IF NOT EXISTS idx_inventory_created_by ON inventory(created_by);
