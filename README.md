# BedcoreProtect
[![](https://poggit.pmmp.io/shield.dl.total/BedcoreProtect)](https://poggit.pmmp.io/p/BedcoreProtect)
[![](https://poggit.pmmp.io/shield.state/BedcoreProtect)](https://poggit.pmmp.io/p/BedcoreProtect)
[![](https://poggit.pmmp.io/shield.api/BedcoreProtect)](https://poggit.pmmp.io/p/BedcoreProtect)
[![Discord](https://img.shields.io/discord/620519017148579841.svg?label=&logo=discord&logoColor=ffffff&color=7389D8&labelColor=6A7EC2)](https://discord.gg/Uf6U78g)

BedcoreProtect is a fast, efficient, data logging and anti-griefing tool for PocketMine servers. Rollback and restore any amount of damage.

## Features
- Fast efficient data logging.
- Fast rollbacks.
- No configuration required. Put the plugin on your server, and you're good to go.
- Multi-language support.
- User interface (UI) support.
- SQLite based data storage.
- Optional MySQL support.
- Easy to use commands.
- Perform rollbacks AND restores. Undo any rollback, anytime.
- Easy to use block inspector
- Advanced search-based lookup tool.
- Paginated logs.
- Automatic update checker.
- Multi-world support.
- Enable or disable any aspect of logging in the configuration file.
- Rollback per-player all damage around you.
- Specify certain block types to skip in rollbacks/restores.
- Restrict rollbacks/restores to specific block types
- Log basic player actions (such as when a player opens a door)
- Liquid tracking. Associate liquid flow with players
- Restrict rollbacks/restores to a radius area.
- Able to track blocks that fall off of other blocks. If a player breaks a block that had a sign on it, both the block and the sign can be rolled back.
- Easily delete old log data.
- Safe default parameters.
Rollback or restore multiple players at once.
- Lookup, rollback, or restore by a specific action.
- Exclude multiple blocks.

...and much more!!

## What does it log?
- Log blocks broken by players
- Log blocks placed by players
- Log natural block breakage (ex: if a sign pops off a dirt block that was broken)
- Log bucket usage
- Log liquid flow
- Log explosions
- Log flint & steel
- Log fire igniting blocks
- Log blocks burning up in fires
- Log block movement (Falling sand/gravel)
- Log leaves decay
- Log player interactions
- Log items taken or placed in chests, furnaces, dispensers, etc.
- Log paintings and item frames. (With rollback support!)
- Log entities killed by players (animals/monsters)

...and the list is still expanding!

# Database Minimum Requirements
In case of **MySQL** as storage, the minimum required version must be >= **5.6.4**

# Commands
You can access the following commands by using **/bedcoreprotect**, **/bcp**, **/core** or **/co**.
Running this command without arguments and with the configuration option `enable-ui-menu: true`, it will display a graphic interface to simplify the plugin usage.

The **command permission** is _bcp.command.bedcoreprotect_ (default: operator).

## Command overview
| Command       | Description                   | Permission              | Permission default |
|---------------|-------------------------------|-------------------------|:------------------:|
| /bcp help     | Display a list of commands    | bcp.subcommand.help     | Operator           |
| /bcp lookup   | Lookup block data             | bcp.subcommand.lookup   | Operator           |
| /bcp purge    | Delete old block data         | bcp.subcommand.purge    | Operator           |
| /bcp reload   | Reload the configuration file | bcp.subcommand.reload   | Operator           |
| /bcp inspect  | Toggle the inspector          | bcp.subcommand.inspect  | Operator           |
| /bcp restore  | Restore block data            | bcp.subcommand.restore  | Operator           |
| /bcp rollback | Rollback block data           | bcp.subcommand.rollback | Operator           |
| /bcp show     | View the plugin status        | bcp.subcommand.show     | Operator           |
| /bcp status   | View the plugin status        | bcp.subcommand.status   | Operator           |

### Command shortcuts
| Command   | Description                                       | Permission          | Permission default |
|-----------|---------------------------------------------------|---------------------|:------------------:|
| /bcp near | Performs a lookup with radius 10                  | bcp.subcommand.near | Operator           |
| /bcp undo | Revert a rollback/restore via the opposite action | bcp.subcommand.undo | Operator           |

## Command details
_Detailed commands information are listed below._

### /bcp help
Display a list of commands available in-game.

---

### /bcp lookup \<parameters\>
Perform a lookup returning a page with all blocks data fetched. If multiple pages are returned, see the command [/bcp show](#bcp-show-pagelines) to switch pages.

> **Alias:** /bcp l \<parameters\>

| Parameter | Mandatory          |
|-----------|:------------------:|
| time      | YES                |
| world     | YES (only console) |
| radius    | NO                 |
| users     | NO                 |
| actions   | NO                 |
| include   | NO                 |
| exclude   | NO                 |

---
### /bcp purge \<parameters\>
Purge old block data. Useful for freeing up space on your HDD if you don't need the older data.

| Parameter | Mandatory |
|-----------|:---------:|
| time      | YES       |
| world     | NO        |

For example, `/bcp purge t=30d` will delete all data older than one month, and only keep the last 30 days of data.

#### Purging Worlds
You can also optionally specify a world where delete the data.
For example, `/bcp purge t=30d w=faction_world` will delete all data older than one month in the Faction world, without deleting data in any other worlds.

You can also add `#optimize` to the end of the command (e.g. `/bcp purge t=30d #optimize`) will also optimize your tables and reclaim disk space.

---
### /bcp reload
Reloads the configuration file.

---

### /bcp inspect
Enable the inspector. Type the command again to disable it.

> **Alias:** /bcp i
---

### /bcp restore \<parameters\>
Perform a rollback. _Rollbacks can be used to revert player actions._

> **Alias:** /bcp rs \<parameters\>

| Parameter | Mandatory          |
|-----------|:------------------:|
| time      | YES                |
| world     | YES (only console) |
| radius    | YES                |
| users     | NO                 |
| actions   | NO                 |
| include   | NO                 |
| exclude   | NO                 |
---

### /bcp rollback \<parameters\>
Perform a restore. _Restoring can be used to undo rollbacks or to restore player actions._

> **Alias:** /bcp rb \<parameters\>

| Parameter | Mandatory          |
|-----------|:------------------:|
| time      | YES                |
| world     | YES (only console) |
| radius    | YES                |
| users     | NO                 |
| actions   | NO                 |
| include   | NO                 |
| exclude   | NO                 |
---

### /bcp show \<page\>:\<lines\>
Allow switching page when multiple pages are returned from the [/bcp lookup](#bcp-lookup-parameters) command.
To change the number of lines displayed on a page, use the command `/bcp show <page>:<lines>`.

> **Alias:** /bcp s

> For example, `/bcp s 2:10` will return 10 lines of data, starting from the second page.
---

### /bcp status
Displays the plugin status and version information.

---

## Parameters overview
| Parameter | Aliases   | Description                    |
|-----------|-----------|--------------------------------|
| users     | user, u   | Specify the user(s).           |
| time      | t         | Specify the amount of time.    |
| radius    | r         | Specify a radius area.         |
| world     | w         | Specify the world.             |
| actions   | action, a | Restrict to a certain actions. |
| include   | i         | Include specific blocks.       |
| exclude   | e         | Exclude specific blocks.       |

## Parameter details
_Detailed commands parameters information are listed below._

### u=\<users\>
_You can specify a single or multiple users or entities._

Examples:
- `u=Notch`
- `u=Notch,shoghicp`
- `u=matcracker,#Zombie`
---

### t=\<time\>
_You can specify weeks, days, hours, minutes, and seconds._

Examples:
- `t=4w5d2h7m20s`
- `t=5d2h`
---

### r=\<radius\>
_A numeric radius targets within that many blocks of your player location._

Examples:
- `r=20` _(target within 20 blocks of your location)_
- `r=#global` _(target the entire server)_
---

### w=\<world\>
_You can specify a single world._

Examples:
- `w=faction`
- `w="my world"` _(if your world name has whitespaces use double quotes)_
---

### a=\<actions\>
_Restrict the command to a specific action._

| Action     | Description                       |
|------------|-----------------------------------|
| block      | Placed/Broken blocks              |
| +block     | Placed blocks                     |
| -block     | Broken blocks                     |
| click      | Player interactions               |
| container  | Items taken from or put in chests |
| +container | Items put in chests               |
| -container | Items taken from chests           |
| kill       | Mobs killed                       |

> For example, if you want to only rollback blocks placed, you would use `a=+block`
---

### i=\<include\>
_Can be used to specify a blocks/items._

Examples:
- `i=stone` _(only includes stone)_
- `w=stone,oak_wood,bedrock` _(specify multiple blocks)_
> You can find a list of blocks at https://minecraft.gamepedia.com/Bedrock_Edition_data_values.
---

### e=\<exclude\>
_Can be used to exclude a blocks/items._

Examples:
- `e=tnt` _(only excludes TNT)_
---

# Donate
You can contribute to the development and maintenance of BedcoreProtect through the following payment methods:
- Bitcoin (BTC): `37BCe87gveKRNPzbcLLyAk2kLVqE53MSZc`
- Ethereum (ETH): `0x6C5c009d5EC990dA2bCFb7A7aeDD369E21C78087`

Your help can make a difference. Thank you.

# FAQ
- **I found a bug, where can I report it?**
  - You can report [here](https://github.com/matcracker/BedcoreProtect/issues/new/choose) by clicking button **"Get Started"** on **Bug report**.
- **Where can I request a new feature?**
  - You can ask a new feature [here](https://github.com/matcracker/BedcoreProtect/issues/new/choose) by clicking button **"Get Started"** on **Feature request**.
