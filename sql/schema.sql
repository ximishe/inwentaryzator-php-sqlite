-- Schema SQL for SQLite (Mobile Inventory System)

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL UNIQUE,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('admin', 'user')),
  active INTEGER NOT NULL DEFAULT 1,
  
  -- Pola pracownika
  first_name TEXT DEFAULT NULL,
  last_name TEXT DEFAULT NULL,
  room TEXT DEFAULT NULL,
  position TEXT DEFAULT NULL,
  status TEXT DEFAULT 'ACTIVE',
  description TEXT DEFAULT NULL,

  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS device_type (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL UNIQUE,
  description TEXT
);

CREATE TABLE IF NOT EXISTS device_field (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  type_id INTEGER NOT NULL,
  name TEXT NOT NULL,
  field_type TEXT NOT NULL, -- text, date, number, select
  display_order INTEGER DEFAULT 0,
  FOREIGN KEY (type_id) REFERENCES device_type(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS device (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  asset_number TEXT NOT NULL UNIQUE,
  type_id INTEGER DEFAULT NULL,
  manufacturer TEXT,
  model TEXT,
  serial_number TEXT,
  purchase_date DATE,
  purchase_price REAL,
  invoice TEXT,
  warranty_until DATE,
  user_id INTEGER DEFAULT NULL,
  room TEXT,
  status TEXT DEFAULT 'ACTIVE',
  description TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (type_id) REFERENCES device_type(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS device_field_value (
  device_id INTEGER NOT NULL,
  field_id INTEGER NOT NULL,
  value TEXT,
  PRIMARY KEY (device_id, field_id),
  FOREIGN KEY (device_id) REFERENCES device(id) ON DELETE CASCADE,
  FOREIGN KEY (field_id) REFERENCES device_field(id) ON DELETE CASCADE
);


CREATE TABLE IF NOT EXISTS device_history (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  device_id INTEGER NOT NULL,
  event_type TEXT NOT NULL,
  date DATETIME DEFAULT CURRENT_TIMESTAMP,
  
  old_user_id INTEGER DEFAULT NULL,
  new_user_id INTEGER DEFAULT NULL,
  
  old_room TEXT,
  new_room TEXT,
  
  old_status TEXT,
  new_status TEXT,
  
  field_name TEXT,
  old_value TEXT,
  new_value TEXT,
  
  description TEXT,
  changed_by TEXT,
  
  FOREIGN KEY (device_id) REFERENCES device(id) ON DELETE CASCADE,
  FOREIGN KEY (old_user_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (new_user_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_device_user_id ON device(user_id);
CREATE INDEX IF NOT EXISTS idx_device_history_device_id ON device_history(device_id);
