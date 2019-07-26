# BedcoreProtect
BedCoreProtect is a fast, efficient, data logging and anti-griefing tool for PocketMine servers. Rollback and restore any amount of damage.

## Commands
The main command is **/bedcoreprotect** but it accepts the folllowing aliases: **/bcp, /core, /co** (**Main permission:** _bcp.command.bedcoreprotect_)

**Quick command overview:**
- **/bcp help - _Display a list of commands_** (**Permission:** _bcp.subcommand.help_)
- **/bcp inspect - _Toggle the inspector mode_** (**Permission:** _bcp.subcommand.inspect_)
- **/bcp rollback \<params> - _Rollback block data_** (**Permission:** _bcp.subcommand.rollback_)
- **/bcp restore \<params> - _Restore block data_** (**Permission:** _bcp.subcommand.restore_)
- **/bcp lookup \<params> - _Advanced block data lookup_** (**Permission:** _bcp.subcommand.lookup_)
- **/bcp purge \<params> - _Delete old block data_** (**Permission:** _bcp.subcommand.purge_)
- **/bcp reload - _Reload the configuration file_** (**Permission:** _bcp.subcommand.reload_)
- **/bcp status - _View the plugin status_** (**Permission:** _bcp.subcommand.status_)

**Shortcut commands:**
- **/bcp near \[value]**: _Performs a lookup with radius (default 5)_ (**Permission:** _bcp.subcommand.near_)
---
**Advanced command overview:**
> **/bcp help**<br>
_Diplay a list of commands in-game_

> **/bpc inspect**<br>
_Enable the inspector. Type the command again to disable it. You can also use just **"/bcp i"**_

> **/bcp rollback u=\<user> t=\<time> r=\<radius> a=\<action> b=\<blocks> e=\<exclude>**<br>
_Nearly all of the parameters are optional. Shortcut: **"/bcp rb"**._

>>**u=\<user>** - Specify a user to rollback.<br>
_Example: u=Shoghi u=Shoghi,#zombie_

>>**t=\<time>** - Specify the amount of time to rollback.<br>
You can specify weeks,days,hours,minutes, and seconds.<br><br>
_Example: t=4w5d2h7m20s_<br><br>
You can pick and choose time amounts. <br>
_Example: t=5d2h_

>>**r=\<radius>** - Specify a radius.<br>
You can use this to only rollback blocks near you.<br><br>
For example, the following would only rollback damage within 10 blocks of where you are standing: r=10

>>**a=\<action>** - Restrict the lookup to a certain action.<br>
For example, if you wanted to only rollback blocks placed, you would use a:+block<br><br>
Here's a list of all the actions:<br>
  • a=block (blocks placed/broken)<br>
  • a=+block (blocks placed)<br>
  • a=-block (blocks broken)<br>
  • a=click (player interactions)<br>
  • a=container (items taken from or put in chests, etc.)<br>
  • a=+container (items put in chests, etc.) <br>
  • a=-container (items taken from chests, etc.)<br>
  • a=kill (mobs/animals killed)<br>
  
>>**b=\<blocks>** - Restrict the rollback to certain block types.<br>
For example, if you wanted to only rollback stone, you would use b=1<br>
You can specify multiple items, such as b=1,5,7<br>
or b=stone,planks,bedrock<br><br>
You can find a list of block type IDs at https://minecraft.gamepedia.com/Bedrock_Edition_data_values

>>**e=\<exclude>** - Exclude certain block types from the rollback.<br>
For example, if you don't want TNT to come back during a rollback, you would type e:46

> **/bpc restore u=\<user> t=\<time> r=\<radius> a=\<action> b=\<blocks> e=\<exclude>**<br>
_Same parameters as /bcp rollback. Shortcut: **"/bcp rs"**._<br><br>
Restoring can be used to undo rollbacks.

> **/bcp lookup u=\<user> t=\<time> r=\<radius> a=\<action> b=\<blocks> e=\<exclude>**<br>
_Search through block data using the same parameters as /bcp rollback. Shortcut: **"/bcp l"**._<br><br>
If multiple pages are returned, use the command **"/bcp lookup \<page>"** to switch pages.<br>
To change the number of lines displayed on a page, use the command **"/bcp lookup \<page>:\<lines>"**.<br><br>
For example, **"/bcp l 1:10"** will return 10 lines of data, starting at the first page.

> **/bcp purge t=\<time>**<br>
_Purge old block data. Useful for freeing up space on your HDD if you don't need the older data._<br><br>
For example, "/co purge t:30d" will delete all data older than one month, and only keep the last 30 days of data.

### Examples
Soon
