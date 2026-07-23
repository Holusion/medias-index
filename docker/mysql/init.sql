-- Runs once, when the data volume is first created.
-- The integration tests need a database they can wipe without touching the one
-- used for local browsing.
CREATE DATABASE IF NOT EXISTS medias_index_test
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON medias_index_test.* TO 'medias'@'%';
FLUSH PRIVILEGES;
