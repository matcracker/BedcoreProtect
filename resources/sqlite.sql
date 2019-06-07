-- #!mysql
-- #{pcp
-- #    {init
-- #        {players
CREATE TABLE IF NOT EXISTS players
(
    xuid VARCHAR(7) AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(16) UNIQUE NOT NULL
)