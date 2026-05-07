-- Create databases for each service
CREATE DATABASE IF NOT EXISTS users_db;
CREATE DATABASE IF NOT EXISTS orders_db;
CREATE DATABASE IF NOT EXISTS notifications_db;

-- Grant permissions
GRANT ALL PRIVILEGES ON users_db.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON orders_db.* TO 'root'@'%';
GRANT ALL PRIVILEGES ON notifications_db.* TO 'root'@'%';
FLUSH PRIVILEGES;
