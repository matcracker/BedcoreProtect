-- #!mysql
-- #{bcp
-- #    {table
-- #        {entities
CREATE TABLE IF NOT EXISTS "entities"
(
    uuid        VARCHAR(36) UNIQUE NOT NULL PRIMARY KEY,
    entity_name VARCHAR(16)        NOT NULL,
    address     VARCHAR(15) DEFAULT '127.0.0.1'
);
-- #        }
-- #        {blocks
CREATE TABLE IF NOT EXISTS "blocks"
(
    id         UNSIGNED INTEGER    NOT NULL,
    damage     UNSIGNED TINYINT(2) NOT NULL,
    block_name VARCHAR(30)         NOT NULL,
    PRIMARY KEY (id, damage)
);
-- #        }
-- #        {log_history
CREATE TABLE IF NOT EXISTS "log_history"
(
    log_id     UNSIGNED BIG INT NOT NULL PRIMARY KEY AUTOINCREMENT,
    who        VARCHAR(36)      NOT NULL,
    x          BIGINT           NOT NULL,
    y          TINYINT UNSIGNED NOT NULL,
    z          BIGINT           NOT NULL,
    world_name VARCHAR(255)     NOT NULL,
    action     TINYINT UNSIGNED NOT NULL,
    time       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    "rollback" BOOLEAN   DEFAULT FALSE,
    FOREIGN KEY (who) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {blocks_log
CREATE TABLE IF NOT EXISTS "blocks_log"
(
    history_id       UNSIGNED BIG INT,
    old_block_id     UNSIGNED INTEGER    NOT NULL,
    old_block_damage UNSIGNED TINYINT(2) NOT NULL,
    new_block_id     UNSIGNED INTEGER    NOT NULL,
    new_block_damage UNSIGNED TINYINT(2) NOT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE,
    FOREIGN KEY (old_block_id, old_block_damage) REFERENCES "blocks" (id, damage),
    FOREIGN KEY (new_block_id, new_block_damage) REFERENCES "blocks" (id, damage)
);
-- #        }
-- #        {entities_log
CREATE TABLE IF NOT EXISTS "entities_log"
(
    history_id      UNSIGNED BIG INT,
    entityfrom_uuid VARCHAR(36) NOT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE,
    FOREIGN KEY (entityfrom_uuid) REFERENCES "entities" (uuid)
);
-- #        }
-- #        {signs_log
CREATE TABLE IF NOT EXISTS "signs_log"
(
    history_id UNSIGNED BIG INT,
    text_lines TEXT,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #        {inventories_log
CREATE TABLE IF NOT EXISTS "inventories_log"
(
    history_id      UNSIGNED BIG INT,
    inventory       VARCHAR(50)         NOT NULL,
    slot            UNSIGNED TINYINT    NOT NULL,
    old_item_id     UNSIGNED INTEGER    NOT NULL,
    old_item_damage UNSIGNED TINYINT(2) NOT NULL,
    old_amount      UNSIGNED TINYINT    NOT NULL,
    new_item_id     UNSIGNED INTEGER    NOT NULL,
    new_item_damage UNSIGNED TINYINT(2) NOT NULL,
    new_amount      UNSIGNED TINYINT    NOT NULL,
    FOREIGN KEY (history_id) REFERENCES "log_history" (log_id) ON DELETE CASCADE
);
-- #        }
-- #    }
-- #    {add
-- #        {entity
-- #            :uuid string
-- #            :name string
-- #            :address string 127.0.0.1
INSERT OR
REPLACE INTO "entities" (uuid, entity_name, address)
VALUES (:uuid, :name, :address);
-- #        }
-- #        {block
-- #            :id int
-- #            :damage int
-- #            :name string
INSERT OR
REPLACE INTO "blocks" (id, damage, block_name)
VALUES (:id, :damage, :name);
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
-- #                :old_id int
-- #                :old_damage int
-- #                :new_id int
-- #                :new_damage int
INSERT INTO "blocks_log"(history_id, old_block_id, old_block_damage, new_block_id, new_block_damage)
VALUES (LAST_INSERT_ROWID(),
        (SELECT id FROM "blocks" WHERE blocks.id = :old_id AND damage = :old_damage),
        (SELECT damage FROM "blocks" WHERE blocks.id = :old_id AND damage = :old_damage),
        (SELECT id FROM "blocks" WHERE blocks.id = :new_id AND damage = :new_damage),
        (SELECT damage FROM "blocks" WHERE blocks.id = :new_id AND damage = :new_damage));
-- #            }
-- #            {to_entity
-- #                :uuid string
INSERT INTO "entities_log"(history_id, entityfrom_uuid)
VALUES (LAST_INSERT_ROWID(), (SELECT uuid FROM entities WHERE uuid = :uuid));
-- #            }
-- #            {to_sign
-- #                :lines string
INSERT INTO "signs_log"(history_id, text_lines)
VALUES (LAST_INSERT_ROWID(), :lines);
-- #            }
-- #            {to_inventory
-- #                :inventory_name string
-- #                :slot int
-- #                :old_item_id int
-- #                :old_item_damage int
-- #                :old_amount int
-- #                :new_item_id int
-- #                :new_item_damage int
-- #                :new_amount int
INSERT INTO "inventories_log"(history_id, inventory, slot, old_item_id, old_item_damage, old_amount, new_item_id,
                              new_item_damage, new_amount)
VALUES (LAST_INSERT_ROWID(), :inventory_name, :slot, :old_item_id, :old_item_damage, :old_amount, :new_item_id,
        :new_item_damage, :new_amount);
-- #            }
-- #        }
-- #    }
-- #    {get
-- #        {log
-- #            {last_id
SELECT MAX(log_id) AS lastId
FROM "log_history";
-- #            }
-- #            {sign
-- #                :id int
SELECT text_lines
FROM "signs_log"
WHERE history_id = :id;
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
       bl.old_block_damage,
       bl.new_block_id,
       bl.new_block_damage,
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
  AND action = 3
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
       bl.old_block_damage,
       bl.new_block_id,
       bl.new_block_damage,
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
  AND action BETWEEN 0 AND 3
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
       il.old_item_damage,
       il.old_amount,
       il.new_item_id,
       il.new_item_damage,
       il.new_amount,
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
  AND (action = 4 OR action = 5)
ORDER BY time DESC;
-- #            }
-- #        }
-- #    }
-- #    {purge
-- #        :time int
DELETE
FROM log_history
WHERE time < DATETIME(time, FROM_UNIXTIME(UNIX_TIMESTAMP() - :time);
-- #    }
-- #}