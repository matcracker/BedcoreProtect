-- #!sqlite
-- #{bcp
-- #    {table
-- #        {entities
CREATE TABLE IF NOT EXISTS "entities"
(
    uuid             VARCHAR(36) UNIQUE PRIMARY KEY NOT NULL,
    entity_name      VARCHAR(16)                    NOT NULL,
    entity_classpath TEXT                           NOT NULL,
    address          VARCHAR(15) DEFAULT '127.0.0.1' NOT NULL
);
-- #        }
-- #        {log_history
CREATE TABLE IF NOT EXISTS "log_history"
(
    log_id     INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
    who        VARCHAR(36)                       NOT NULL,
    x          BIGINT                            NOT NULL,
    y          TINYINT UNSIGNED                  NOT NULL,
    z          BIGINT                            NOT NULL,
    world_name VARCHAR(255)                      NOT NULL,
    action     TINYINT UNSIGNED                  NOT NULL,
    time       TIMESTAMP  DEFAULT (STRFTIME('%Y-%m-%d %H:%M:%f', 'now', 'localtime')) NOT NULL,
    "rollback" TINYINT(1) DEFAULT 0 NOT NULL,
    FOREIGN KEY (who) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {blocks_log
CREATE TABLE IF NOT EXISTS "blocks_log"
(
    history_id     UNSIGNED BIG INT    NOT NULL,
    old_block_id   UNSIGNED INTEGER    NOT NULL,
    old_block_meta UNSIGNED TINYINT(2) NOT NULL,
    old_block_nbt  BLOB DEFAULT NULL,
    new_block_id   UNSIGNED INTEGER    NOT NULL,
    new_block_meta UNSIGNED TINYINT(2) NOT NULL,
    new_block_nbt  BLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {entities_log
CREATE TABLE IF NOT EXISTS "entities_log"
(
    history_id      UNSIGNED BIG INT NOT NULL,
    entityfrom_uuid VARCHAR(36)      NOT NULL,
    entityfrom_id   UNSIGNED INTEGER NOT NULL,
    entityfrom_nbt  BLOB DEFAULT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE,
    FOREIGN KEY (entityfrom_uuid) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {inventories_log
CREATE TABLE IF NOT EXISTS "inventories_log"
(
    history_id      UNSIGNED BIG INT NOT NULL,
    slot            UNSIGNED TINYINT NOT NULL,
    old_item_id     UNSIGNED INTEGER    DEFAULT 0 NOT NULL,
    old_item_meta   UNSIGNED TINYINT(2) DEFAULT 0 NOT NULL,
    old_item_nbt    BLOB                DEFAULT NULL,
    old_item_amount UNSIGNED TINYINT    DEFAULT 0 NOT NULL,
    new_item_id     UNSIGNED INTEGER    DEFAULT 0 NOT NULL,
    new_item_meta   UNSIGNED TINYINT(2) DEFAULT 0 NOT NULL,
    new_item_nbt    BLOB                DEFAULT NULL,
    new_item_amount UNSIGNED TINYINT    DEFAULT 0 NOT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {db_status
CREATE TABLE IF NOT EXISTS status
(
    only_one_row TINYINT(1) PRIMARY KEY DEFAULT 1 NOT NULL,
    version      VARCHAR(20) NOT NULL,
    upgraded_on  TIMESTAMP(6)           DEFAULT (STRFTIME('%Y-%m-%d %H:%M:%f', 'now', 'localtime')) NOT NULL,
    CHECK (only_one_row)
);
-- #        }
-- #    }
-- #    {transaction
-- #        {begin
BEGIN TRANSACTION;
-- #        }
-- #        {end
END TRANSACTION;
-- #        }
-- #    }
-- #    {add
-- #        {entity
-- #            :uuid string
-- #            :name string
-- #            :path string
-- #            :address string 127.0.0.1
INSERT OR
REPLACE
INTO "entities" (uuid, entity_name, entity_classpath, address)
VALUES (:uuid, :name, :path, :address);
-- #        }
-- #        {db_version
-- #            :version string
INSERT OR IGNORE
INTO status (version)
VALUES (:version);
-- #        }
-- #        {log
-- #            {main
-- #                :uuid string
-- #                :x int
-- #                :y int
-- #                :z int
-- #                :world_name string
-- #                :action int
INSERT INTO "log_history"(who, x, y, z, world_name,
                          action)
VALUES ((SELECT uuid FROM entities WHERE uuid = :uuid), :x, :y, :z, :world_name, :action);
-- #            }
-- #            {to_block
-- #                :old_block_id int
-- #                :old_block_meta int
-- #                :old_block_nbt ?string
-- #                :new_block_id int
-- #                :new_block_meta int
-- #                :new_block_nbt ?string
INSERT INTO "blocks_log"(history_id, old_block_id, old_block_meta, old_block_nbt, new_block_id, new_block_meta,
                         new_block_nbt)
VALUES (LAST_INSERT_ROWID(), :old_block_id, :old_block_meta, :old_block_nbt, :new_block_id, :new_block_meta,
        :new_block_nbt);
-- #            }
-- #            {to_entity
-- #                :uuid string
-- #                :id int
-- #                :nbt ?string
INSERT INTO "entities_log"(history_id, entityfrom_uuid, entityfrom_id, entityfrom_nbt)
VALUES (LAST_INSERT_ROWID(), (SELECT uuid FROM entities WHERE uuid = :uuid), :id, :nbt);
-- #            }
-- #            {to_inventory
-- #                :slot int
-- #                :old_item_id int 0
-- #                :old_item_meta int 0
-- #                :old_item_nbt ?string
-- #                :old_item_amount int 0
-- #                :new_item_id int 0
-- #                :new_item_meta int 0
-- #                :new_item_nbt ?string
-- #                :new_item_amount int 0
INSERT INTO "inventories_log"(history_id, slot, old_item_id, old_item_meta, old_item_nbt, old_item_amount, new_item_id,
                              new_item_meta, new_item_nbt, new_item_amount)
VALUES (LAST_INSERT_ROWID(), :slot, :old_item_id, :old_item_meta, :old_item_nbt, :old_item_amount, :new_item_id,
        :new_item_meta, :new_item_nbt, :new_item_amount);
-- #            }
-- #            {update_entity_id
-- #                :log_id int
-- #                :entity_id int
UPDATE entities_log
SET entityfrom_id = :entity_id
WHERE history_id = :log_id;
-- #            }
-- #            {update_db_version
-- #                :version string
UPDATE status
SET version     = :version,
    upgraded_on = (STRFTIME('%Y-%m-%d %H:%M:%f', 'now', 'localtime'));
-- #            }
-- #        }
-- #    }
-- #    {get
-- #        {db_status
SELECT *
FROM status
LIMIT 1;
-- #        }
-- #        {log
-- #            {last_id
SELECT MAX(log_id) AS lastId
FROM "log_history";
-- #            }
-- #            {block
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
SELECT bl.old_block_id,
       bl.old_block_meta,
       bl.old_block_nbt,
       bl.new_block_id,
       bl.new_block_meta,
       bl.new_block_nbt,
       e.entity_name AS entity_from,
       x,
       y,
       z,
       world_name,
       action,
       time,
       "rollback"
FROM "log_history"
         INNER JOIN "entities" e ON log_history.who = e.uuid
         INNER JOIN "blocks_log" bl ON log_history.log_id = bl.history_id
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 0 AND 2
ORDER BY time DESC;
-- #            }
-- #            {entity
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
SELECT e1.entity_name AS entity_from,
       e2.entity_name AS entity_to,
       x,
       y,
       z,
       world_name,
       action,
       time,
       "rollback"
FROM "log_history"
         INNER JOIN "entities" e1 ON log_history.who = e1.uuid
         INNER JOIN "entities_log" el ON log_history.log_id = el.history_id
         INNER JOIN "entities" e2 ON el.entityfrom_uuid = e2.uuid
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 3 AND 5
ORDER BY time DESC;
-- #            }
-- #            {near
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
SELECT bl.old_block_id,
       bl.old_block_meta,
       bl.old_block_nbt,
       bl.new_block_id,
       bl.new_block_meta,
       bl.new_block_nbt,
       e1.entity_name AS entity_from,
       e2.entity_name AS entity_to,
       x,
       y,
       z,
       world_name,
       action,
       time,
       "rollback"
FROM "log_history"
         LEFT JOIN "entities" e1 ON log_history.who = e1.uuid
         LEFT JOIN "entities_log" el ON log_history.log_id = el.history_id
         LEFT JOIN "entities" e2 ON el.entityfrom_uuid = e2.uuid
         LEFT JOIN "blocks_log" bl ON log_history.log_id = bl.history_id
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 0 AND 5
ORDER BY time DESC;
-- #            }
-- #            {transaction
-- #                :min_x int
-- #                :max_x int
-- #                :min_y int
-- #                :max_y int
-- #                :min_z int
-- #                :max_z int
-- #                :world_name string
SELECT il.old_item_id,
       il.old_item_meta,
       il.old_item_nbt,
       il.old_item_amount,
       il.new_item_id,
       il.new_item_meta,
       il.new_item_nbt,
       il.new_item_amount,
       e.entity_name AS entity_from,
       x,
       y,
       z,
       world_name,
       action,
       time,
       "rollback"
FROM "log_history"
         INNER JOIN "entities" e ON log_history.who = e.uuid
         INNER JOIN "inventories_log" il on log_history.log_id = il.history_id
WHERE (x BETWEEN :min_x AND :max_x)
  AND (y BETWEEN :min_y AND :max_y)
  AND (z BETWEEN :min_z AND :max_z)
  AND world_name = :world_name
  AND action BETWEEN 6 AND 7
ORDER BY time DESC;
-- #            }
-- #        }
-- #    }
-- #    {purge
-- #        :time int
DELETE
FROM log_history
WHERE time < DATETIME(((SELECT strftime('%s', 'now')) - :time), 'unixepoch', 'localtime');
-- #    }
-- #}