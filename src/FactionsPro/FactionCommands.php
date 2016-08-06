<?php

namespace FactionsPro;

use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\level;
use pocketmine\level\Position;

class FactionCommands {

    public $plugin;

    public function __construct(FactionMain $pg) {
        $this->plugin = $pg;
    }

    public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
        if ($sender instanceof Player) {
            $player = $sender->getPlayer()->getName();
            if (strtolower($command->getName('guilds'))) {
                if (empty($args)) {
                    $sender->sendMessage($this->plugin->formatMessage("Please use /guilds help for a list of commands"));
                    return true;
                }
                if (count($args == 2)) {

                    ///////////////////////////////// WAR /////////////////////////////////

                    if ($args[0] == "war") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds war <guilds name:tp>"));
                            return true;
                        }
                        if (strtolower($args[1]) == "tp") {
                            foreach ($this->plugin->wars as $r => $f) {
                                $fac = $this->plugin->getPlayerFaction($player);
                                if ($r == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$f][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
                                    return;
                                }
                                if ($f == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$r][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                                    return;
                                }
                            }
                            $sender->sendMessage("You must be in a war to do that");
                            return true;
                        }
                        if (!(ctype_alnum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("You may only use letters and numbers"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Guilds does not exist"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Only your guilds leader may start wars"));
                            return true;
                        }
                        if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds is not an enemy of $args[1]"));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $sFaction = $this->plugin->getPlayerFaction($player);
                            foreach ($this->plugin->war_req as $r => $f) {
                                if ($r == $args[1] && $f == $sFaction) {
                                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                        $task = new FactionWar($this->plugin, $r);
                                        $handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                        $task->setHandler($handler);
                                        $p->sendMessage("The war against $factionName and $sFaction has started!");
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                                            $this->plugin->war_players[$sFaction][] = $p->getName();
                                        }
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                            $this->plugin->war_players[$factionName][] = $p->getName();
                                        }
                                    }
                                    $this->plugin->wars[$factionName] = $sFaction;
                                    unset($this->plugin->war_req[strtolower($args[1])]);
                                    return true;
                                }
                            }
                            $this->plugin->war_req[$sFaction] = $factionName;
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                    if ($this->plugin->getLeader($factionName) == $p->getName()) {
                                        $p->sendMessage("$sFaction wants to start a war, '/guilds war $sFaction' to start!");
                                        $sender->sendMessage("Guilds war requested");
                                        return true;
                                    }
                                }
                            }
                            $sender->sendMessage("Guilds leader is not online.");
                            return true;
                        }
                    }

                    /////////////////////////////// CREATE ///////////////////////////////

                    if ($args[0] == "create") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds create <guilds name>"));
                            return true;
                        }
                        if (!(ctype_alnum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("You may only use letters and numbers"));
                            return true;
                        }
                        if ($this->plugin->isNameBanned($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("This name is not allowed"));
                            return true;
                        }
                        if ($this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The Guilds already exists"));
                            return true;
                        }
                        if (strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
                            $sender->sendMessage($this->plugin->formatMessage("That name is too long, please try again"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must leave the guilds first"));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $rank = "Leader";
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", $player);
                            $stmt->bindValue(":faction", $factionName);
                            $stmt->bindValue(":rank", $rank);
                            $result = $stmt->execute();
                            $this->plugin->updateAllies($factionName);
                            $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
                            $this->plugin->updateTag($sender->getName());
                            $sender->sendMessage($this->plugin->formatMessage("Guilds created", true));
                            return true;
                        }
                    }

                    /////////////////////////////// INVITE ///////////////////////////////

                    if ($args[0] == "invite") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds invite <player>"));
                            return true;
                        }
                        if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($player))) {
                            $sender->sendMessage($this->plugin->formatMessage("Guilds is full, please kick players to make room"));
                            return true;
                        }
                        $invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                        if (!($invited instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Player not online"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($invited) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Player is currently in a guilds"));
                            return true;
                        }
                        if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite")) {
                            if (!($this->plugin->isOfficer($player) || $this->plugin->isLeader($player))) {
                                $sender->sendMessage($this->plugin->formatMessage("Only your guilds leader/assistants can invite"));
                                return true;
                            }
                        }
                        if ($invited->getName() == $player) {

                            $sender->sendMessage($this->plugin->formatMessage("You can't invite yourself to your own guilds!"));
                            return true;
                        }

                        $factionName = $this->plugin->getPlayerFaction($player);
                        $invitedName = $invited->getName();
                        $rank = "Member";

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
                        $stmt->bindValue(":player", $invitedName);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":invitedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("$invitedName has been invited", true));
                        $invited->sendMessage($this->plugin->formatMessage("You have been invited to $factionName. Type '/guilds accept' or '/guilds deny' into chat to accept or deny!", true));
                    }

                    /////////////////////////////// LEADER ///////////////////////////////

                    if ($args[0] == "leader") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds leader <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Add player to guilds first!"));
                            return true;
                        }
                        if (!($this->plugin->getServer()->getPlayerExact($args[1]) instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Player not online"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {

                            $sender->sendMessage($this->plugin->formatMessage("You can't transfer the leadership to yourself"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $player);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();

                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Leader");
                        $result = $stmt->execute();


                        $sender->sendMessage($this->plugin->formatMessage("You are no longer leader", true));
                        $this->plugin->getServer()->getPlayerExact($args[1])->sendMessage($this->plugin->formatMessage("You are now leader of $factionName!", true));
                        $this->plugin->updateTag($sender->getName());
                        $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                    }

                    /////////////////////////////// PROMOTE ///////////////////////////////

                    if ($args[0] == "promote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds promote <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Player is not in this guilds!"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("You can't promote yourself!"));
                            return true;
                        }

                        if ($this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Player is already Assistants!"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Officer");
                        $result = $stmt->execute();
                        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("$args[1] has been promoted to Assistants", true));

                        if ($player instanceof Player) {
                            $player->sendMessage($this->plugin->formatMessage("You were promoted to assistants of $factionName!", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// DEMOTE ///////////////////////////////

                    if ($args[0] == "demote") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds demote <player>"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Player is not in this guilds!"));
                            return true;
                        }

                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("You can't demote yourself!"));
                            return true;
                        }
                        if (!$this->plugin->isOfficer($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Player is already Member!"));
                            return true;
                        }
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                        $stmt->bindValue(":player", $args[1]);
                        $stmt->bindValue(":faction", $factionName);
                        $stmt->bindValue(":rank", "Member");
                        $result = $stmt->execute();
                        $player = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("$args[1] has been demoted to Member", true));
                        if ($player instanceof Player) {
                            $player->sendMessage($this->plugin->formatMessage("You were demoted to member of $factionName!", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// KICK ///////////////////////////////

                    if ($args[0] == "kick") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds kick <player>"));
                            return true;
                        }
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this!"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this!"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Player is not in this guilds!"));
                            return true;
                        }
                        if ($args[1] == $sender->getName()) {
                            $sender->sendMessage($this->plugin->formatMessage("You can't kick yourself!"));
                            return true;
                        }
                        $kicked = $this->plugin->getServer()->getPlayerExact($args[1]);
                        $factionName = $this->plugin->getPlayerFaction($player);
                        $this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("You successfully kicked $args[1]", true));
                        $this->plugin->subtractFactionPower($factionName, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));

                        if ($kicked instanceof Player) {
                            $kicked->sendMessage($this->plugin->formatMessage("You have been kicked from $factionName", true));
                            $this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
                            return true;
                        }
                    }

                    /////////////////////////////// INFO ///////////////////////////////

                    if (strtolower($args[0]) == 'info') {
                        if (isset($args[1])) {
                            if (!(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
                                $sender->sendMessage($this->plugin->formatMessage("Guilds does not exist"));
                                $sender->sendMessage($this->plugin->formatMessage("Make sure the name of the selected guild is ABSOLUTELY EXACT."));
                                return true;
                            }
                            $faction = $args[1];
                            $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                            $array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
                            $message = $array["message"];
                            $leader = $this->plugin->getLeader($faction);
                            $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§eInformation §l§b«" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aGuilds §8: " . TextFormat::GREEN . "§d$faction" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aLeader §8: " . TextFormat::YELLOW . "§d$leader" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aPlayers §8: " . TextFormat::LIGHT_PURPLE . "§d$numPlayers" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aGuildsPoints §8: " . TextFormat::RED . "§d$power" . " " . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aDescription §8: " . TextFormat::AQUA . TextFormat::UNDERLINE . "§d$message" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§eInformation §l§b«" . TextFormat::RESET);
                        } else {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this!"));
                                return true;
                            }
                            $faction = $this->plugin->getPlayerFaction(($sender->getName()));
                            $result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
                            $array = $result->fetchArray(SQLITE3_ASSOC);
                            $power = $this->plugin->getFactionPower($faction);
                            $message = $array["message"];
                            $leader = $this->plugin->getLeader($faction);
                            $numPlayers = $this->plugin->getNumberOfPlayers($faction);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§eInformation §l§b«" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aGuilds §8: " . TextFormat::GREEN . "§d$faction" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aLeader §8: " . TextFormat::YELLOW . "§d$leader" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aPlayers §8: " . TextFormat::LIGHT_PURPLE . "§d$numPlayers" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aGuildsPoints §8: " . TextFormat::RED . "§d$power" . " " . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§aDescription §8: " . TextFormat::AQUA . TextFormat::UNDERLINE . "§d$message" . TextFormat::RESET);
                            $sender->sendMessage(TextFormat::GOLD . TextFormat::ITALIC . "§r§l§b» §r§eInformation §l§b«" . TextFormat::RESET);
                    return true;
                        }
                    }
/*Help Commands*/
                    if (strtolower($args[0]) == "help") {
                        if (!isset($args[1]) || $args[1] == 1) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b««\n§l§b»§r     §dGuilds Help Page §f[§c1ff§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilds about §l§b»§r §aShows Any Information You Need To Know!\n§l§c»§r §e/guilds accept §l§b»§r §aAccept A Guilds Request!\n§l§c»§r §e/guilds create <name> §l§b»§r §aCreate Your Desire Guilds!\n§l§c»§r §e/guilds del §l§b»§r §aDelete Your Own Guilds!\n§l§c»§r §e/guilds demote <player> §l§b»§r §aDemote Your Any Assistance To Members!\n§l§c»§r §e/guilds deny §l§b»§r §aDeny A Guilds Request!");
/*§l§c»§r §e/guilds overclaim [Takeover the plot of the requested faction]\n§l§c»§r §e/guilds claim\n*/                            return true;
                        }
                        if ($args[1] == 2) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dGuilds Help Page §f[§c2ff§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilds help <page> §l§b»§r §aShows A List Of Guilds Help Page!\n§l§c»§r §e/guilds info §l§b»§r §aShows Your Guilds Information!\n§l§c»§r §e/guilds info <faction> §l§b»§r §aShows Targets Guilds Information!\n§l§c»§r §e/guilds invite <player> §l§b»§r §aInvite A Player As A Leader!\n§l§c»§r §e/guilds kick <player> §l§b»§r §aKick/Remove Specific Player From Guilds!\n§l§c»§r §e/guilds leader <player> §l§b»§r §aMake A Player To Be The New LEeader!\n§l§c»§r §e/guilds leave §l§b»§r §aLeave Your Current Guilds!");
                            return true;
                        }
                        if ($args[1] == 3) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dGuilds Help Page §f[§c3ff§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilds members - {Members + Statuses} §l§b»§r §aShows Your Guilds MembersList!\n§l§c»§r §e/guilds assistants - {Assistants + Statuses} §l§b»§r §aShows Your AssistantsList!\n§l§c»§r §e/guilds ourleaders - {Leader + Status} §l§b»§r §aShows Your LeadersList!\n§l§c»§r §e/guilds allies §l§b»§r §aShows The Guild YOU ALLIED!");
                            return true;
                        }
                        if ($args[1] == 4) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dGuilds Help Page §f[§c4ff§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilds desc §l§b»§r §aUpdate The Guilds Description!\n§l§c»§r §e/guilds promote <player> §l§b»§r §aPromote A Members To Assistants!\n§l§c»§r §e/guilds allywith <guilds> §l§b»§r §aRequest An Alliance With A Guilds!\n§l§c»§r §e/guilds breakalliancewith <guilds> §l§b»§r §aBreak The Alliance Contract With A Guilds!\n§l§c»§r §e/guilds allyok §l§b»§r §aAccept An Alliance Request!\n§l§c»§r §e/guilds allyno §l§b»§r §aDenied An Alliance Request!\n§l§c»§r §e/guilds allies <guilds> §l§b»§r §aShows A Specific Guilds Alliance!");
                            return true;
                        }
                        if ($args[1] == 5) {
                            $sender->sendMessage(TextFormat::GOLD . "§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«\n§l§b»§r     §dGuilds Help Page §f[§c5ff§c6§f]       §l§b«\n§l§b»§r§a-=-=-=-=-=-=-=-=-=-=-=-=-=-§l§b«" . TextFormat::RED . "\n§l§c»§r §e/guilds membersof <guilds> §l§b»§r §aShows The List Of A Specific Guilds Members!\n§l§c»§r §e/guilds assistantsof <guilds> §l§b»§r §aShows The List Of A Specific Guilds Assistants!\n§l§c»§r §e/guilds leadersof <guilds> §l§b»§r §aShows The Guilds Leaders List!\n§l§c»§r §e/guilds search <player> §l§b»§r §aSearch The Player Guilds!\n§l§c»§r §e/guilds leaderboards §l§b»§r §aShows Top Ranking Guilds!\n§l§c»§r §e/guilds setef §l§b»§r §aSet Effects For Guilds!\n§l§c»§r §e/guilds efinfo §l§b»§r §aShows Effects Information!\n§l§c»§r §e/guilds getef §l§b»§r §aGets The Effects You Have Setted!");
                            return true;

                        }
                    }
                }
                if (count($args == 1)) {

                    if (strtolower($args[0]) == 'leaderboards') {
                        $this->plugin->sendListOfTop10FactionsTo($sender);
                    }
                    if (strtolower($args[0]) == 'forcedelete') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds forcedelete <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                            return true;
                        }
                        $this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
                        $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
                        $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
                        $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
                        $sender->sendMessage($this->plugin->formatMessage("Unwanted guilds was successfully deleted and their guilds plot was unclaimed!", true));
                    }
                    if (strtolower($args[0]) == 'addgp') {
                        if (!isset($args[1]) or ! isset($args[2])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds addgp <guilds> <GuildsPoints>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist."));
                            return true;
                        }
                        if (!($sender->isOp())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be OP to do this."));
                            return true;
                        }
                        $this->plugin->addFactionPower($args[1], $args[2]);
                        $sender->sendMessage($this->plugin->formatMessage("Successfully added $args[2] GuildsPoints to $args[1]", true));
                    }
                    if (strtolower($args[0]) == 'search') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds search <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The selected player is not in a guilds  or doesn't exist."));
                            $sender->sendMessage($this->plugin->formatMessage("Make sure the name of the selected player is ABSOLUTELY EXACT."));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("-$args[1] is in $faction-", true));
                    }

                    /////////////////////////////// DESCRIPTION ///////////////////////////////

                    if (strtolower($args[0]) == "desc") {
                        if ($this->plugin->isInFaction($sender->getName()) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this!"));
                            return true;
                        }
                        if ($this->plugin->isLeader($player) == false) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be leader to use this"));
                            return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("Type your message in chat. It will not be visible to other players", true));
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
                        $stmt->bindValue(":player", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                    }

                    /////////////////////////////// ACCEPT ///////////////////////////////

                    if (strtolower($args[0]) == "accept") {
                        $player = $sender->getName();
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("You have not been invited to any guilds"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $faction = $array["faction"];
                            $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
                            $stmt->bindValue(":player", ($player));
                            $stmt->bindValue(":faction", $faction);
                            $stmt->bindValue(":rank", "Member");
                            $result = $stmt->execute();
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("You successfully joined $faction", true));
                            $this->plugin->addFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player joined the guilds", true));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Invite has timed out"));
                            $this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
                        }
                    }

                    /////////////////////////////// DENY ///////////////////////////////

                    if (strtolower($args[0]) == "deny") {
                        $player = $sender->getName();
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("You have not been invited to any guilds"));
                            return true;
                        }
                        $invitedTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $invitedTime) <= 60) { //This should be configurable
                            $this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("Invite declined", true));
                            $this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("$player declined the invitation"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Invite has timed out"));
                            $this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
                        }
                    }

                    /////////////////////////////// DELETE ///////////////////////////////

                    if (strtolower($args[0]) == "del") {
                        if ($this->plugin->isInFaction($player) == true) {
                            if ($this->plugin->isLeader($player)) {
                                $faction = $this->plugin->getPlayerFaction($player);
                                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
                                $this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
                                $this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                                $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
                                $sender->sendMessage($this->plugin->formatMessage("Guilds successfully disbanded.", true));
                                $this->plugin->updateTag($sender->getName());
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("You are not leader!"));
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You are not in a guilds!"));
                        }
                    }

                    /////////////////////////////// LEAVE ///////////////////////////////

                    if (strtolower($args[0] == "leave")) {
                        if ($this->plugin->isLeader($player) == false) {
                            $remove = $sender->getPlayer()->getNameTag();
                            $faction = $this->plugin->getPlayerFaction($player);
                            $name = $sender->getName();
                            $this->plugin->db->query("DELETE FROM master WHERE player='$name';");
                            $sender->sendMessage($this->plugin->formatMessage("You successfully left $faction", true));

                            $this->plugin->subtractFactionPower($faction, $this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
                            $this->plugin->updateTag($sender->getName());
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("You must delete the guilds or give leadership to someone else first"));
                        }
                    }

                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if (strtolower($args[0] == "members")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Member");
                    }
                    if (strtolower($args[0] == "membersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds membersof <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Member");
                    }
                    if (strtolower($args[0] == "assistants")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Officer");
                    }
                    if (strtolower($args[0] == "assistantsof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds assistantsof <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "O§f[§c3§§f[§c4§§f[§c5ff§c6§f]§c6§f]§c6§f]icer");
                    }
                    if (strtolower($args[0] == "ourleaders")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Leader");
                    }
                    if (strtolower($args[0] == "leadersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds leadersof <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Leader");
                    }
                    if (strtolower($args[0] == "say")) {
                        if (true) {
                            $sender->sendMessage($this->plugin->formatMessage("/guilds say is disabled"));
                            return true;
                        }
                        if (!($this->plugin->isInFaction($player))) {

                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to send faction messages"));
                            return true;
                        }
                        $r = count($args);
                        $row = array();
                        $rank = "";
                        $f = $this->plugin->getPlayerFaction($player);

                        if ($this->plugin->isOfficer($player)) {
                            $rank = "*";
                        } else if ($this->plugin->isLeader($player)) {
                            $rank = "**";
                        }
                        $message = "-> ";
                        for ($i = 0; $i < $r - 1; $i = $i + 1) {
                            $message = $message . $args[$i + 1] . " ";
                        }
                        $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                        for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
                            $row[$i]['player'] = $resultArr['player'];
                            $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                            if ($p instanceof Player) {
                                $p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "<FM>" . TextFormat::AQUA . " <$rank$f> " . TextFormat::GREEN . "<$player> " . ": " . TextFormat::RESET);
                                $p->sendMessage(TextFormat::ITALIC . TextFormat::DARK_AQUA . $message . TextFormat::RESET);
                            }
                        }
                    }


                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
                    if (strtolower($args[0] == "enemywith")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds enemywith <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds can not enemy with itself"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds is already enemied with $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("The leader of the requested guilds is o§f[§c3§§f[§c4§§f[§c5ff§c6§f]§c6§f]§c6§f]line"));
                            return true;
                        }
                        $this->plugin->setEnemies($fac, $args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("You are now enemies with $args[1]!", true));
                        $leader->sendMessage($this->plugin->formatMessage("The leader of $fac has declared your guilds as an enemy", true));
                    }
                    if (strtolower($args[0] == "allywith")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds allywith <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds can not ally with itself"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds is already allied with $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("The leader of the requested guilds is o§f[§c3§§f[§c4§§f[§c5ff§c6§f]§c6§f]§c6§f]line"));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds has the maximum amount of allies", false));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has the maximum amount of allies", false));
                            return true;
                        }
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
                        $stmt->bindValue(":player", $leader->getName());
                        $stmt->bindValue(":faction", $args[1]);
                        $stmt->bindValue(":requestedby", $sender->getName());
                        $stmt->bindValue(":timestamp", time());
                        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("You requested to ally with $args[1]!\nWait for the leader's response...", true));
                        $leader->sendMessage($this->plugin->formatMessage("The leader of $fac requested an alliance.\nType /guilds allyok to accept or /guilds allyno to deny.", true));
                    }
                    if (strtolower($args[0] == "breakalliancewith")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /guilds breakalliancewith <guilds>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds can not break alliance with itself"));
                            return true;
                        }
                        if (!$this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds is not allied with $args[1]"));
                            return true;
                        }

                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->deleteAllies($fac, $args[1]);
                        $this->plugin->deleteAllies($args[1], $fac);
                        $this->plugin->subtractFactionPower($fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->subtractFactionPower($args[1], $this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("Your faction $fac is no longer allied with $args[1]", true));
                        if ($leader instanceof Player) {
                            $leader->sendMessage($this->plugin->formatMessage("The leader of $fac broke the alliance with your guilds $args[1]", false));
                        }

                    }
                    if (strtolower($args[0] == "allies")) {
                        if (!isset($args[1])) {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                                return true;
                            }

                            $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
                            $this->plugin->getAllAllies($sender, $this->plugin->getPlayerFaction($player));
                        } else {
                            if (!$this->plugin->factionExists($args[1])) {
                                $sender->sendMessage($this->plugin->formatMessage("The requested guilds doesn't exist"));
                                return true;
                            }
                            $this->plugin->updateAllies($args[1]);
                            $this->plugin->getAllAllies($sender, $args[1]);
                        }
                    }
                    if (strtolower($args[0] == "allyok")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has not been requested to ally with any guilds"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
                            $this->plugin->setAllies($requested_fac, $sender_fac);
                            $this->plugin->setAllies($sender_fac, $requested_fac);
                            $this->plugin->addFactionPower($sender_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->addFactionPower($requested_fac, $this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $this->plugin->updateAllies($requested_fac);
                            $this->plugin->updateAllies($sender_fac);
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has successfully allied with $requested_fac", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has accepted the alliance!", true));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Request has timed out"));
                            $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
                        }
                    }
                    if (strtolower($args[0]) == "allyno") {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be a leader to do this"));
                            return true;
                        }
                        $lowercaseName = ($player);
                        $result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
                        $array = $result->fetchArray(SQLITE3_ASSOC);
                        if (empty($array) == true) {
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has not been requested to ally with any guilds"));
                            return true;
                        }
                        $allyTime = $array["timestamp"];
                        $currentTime = time();
                        if (($currentTime - $allyTime) <= 60) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
                            $this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds has successfully declined the alliance request.", true));
                            $this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("$player from $sender_fac has declined the alliance!"));
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("Request has timed out"));
                            $this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
                        }
                    }


///////////////////////////////////////
                    ///////////////EFFFECTS?//////////////////////////
                    $amp = 0;
                    $strengthperkill = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                    $lvl = array($strengthperkill*100,$strengthperkill*500,$strengthperkill*1000,$strengthperkill*5000);
                    if(strtolower($args[0]) == 'setef'){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("/guilds setef <fast:str:jump:haste:res:life>"));
							return true;
                        }
                        if(!$this->plugin->isInFaction($player)){
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this!"));
							return true;
                        }
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("You must be leader to use this."));
							return true;
						}
                        $factionname = $this->plugin->getPlayerFaction($player);
                        $factionstrength = $this->plugin->getFactionPower($factionname);
                        $strengthperkill = $this->plugin->prefs->get("PowerGainedPerKillingAnEnemy");
                        if($factionstrength < $lvl[0]){
                            $needed_power = $lvl[0];
							$sender->sendMessage($this->plugin->formatMessage("Your guilds doesn't have enough GuildsPoints to select an e§f[§c3§§f[§c4§§f[§c5ff§c6§f]§c6§f]§c6§f]ect."));
							$sender->sendMessage($this->plugin->formatMessage("$needed_power GuildsPoints is required but your guilds has only $factionstrength GuildsPoints."));
							return true;
                        }
                        if(!(in_array(strtolower($args[1]),array("fast","str","jump","haste","res","life")))){
                            $sender->sendMessage($this->plugin->formatMessage("The '$args[1]' mode is not available."));
                            $sender->sendMessage($this->plugin->formatMessage("/guilds setef <fast:str:jump:haste:res:life>"));
							return true;
                        }
                        $this->plugin->addEffectTo($this->plugin->getPlayerFaction($player),strtolower($args[1]));
                        $this->plugin->updateTagsAndEffectsOf($factionname);
                        $sender->sendMessage($this->plugin->formatMessage("Successfully updated your guilds's effect.",true));
                        return true;
                    }
                    if(strtolower($args[0]) == 'efinfo'){
                        for($i=0;$i<4;$i++){
                            $s = $i + 1;
                            $sender->sendMessage($this->plugin->formatMessage("Lvl $s effects unlock at $lvl[$i] GuildsPoints",true));
                        }
                        return true;
                    }
                    if(strtolower($args[0]) == 'getef'){
                        if(!$this->plugin->isInFaction($player)){
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a guilds to use this!"));
							return true;
                        }
                        $factionname = $this->plugin->getPlayerFaction($player);
                        $factionstrength = $this->plugin->getFactionPower($factionname);
                        if($this->plugin->getEffectOf($factionname) == "none"){
                            $sender->sendMessage($this->plugin->formatMessage("Your guilds's effect is not set. Set it by typing /guilds setef <effect>"));
                            return true;
                        }
                        $sender->removeAllEffects();
                        for($i=0;$i<4;$i++){
                            if($factionstrength >= $lvl[$i]){
                                $amp = $i;
                            }
                        }
                        switch($this->plugin->getEffectOf($factionname)){
                            case "fast":
                                $sender->addEffect(Effect::getEffect(1)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "str":
                                $sender->addEffect(Effect::getEffect(5)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "jump":
                                $sender->addEffect(Eddect::getEffect(8)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "haste":
                                $sender->addEffect(Effect::getEffect(3)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "res":
                                $sender->addEffect(Effect::getEffect(11)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                            case "life":
                                $sender->addEffect(Effect::getEffect(21)->setDuration(PHP_INT_MAX)->setAmplifier($amp)->setVisible(false));
                                break;
                        }  
                        $sender->sendMessage($this->plugin->formatMessage("Enjoy your effect!", true));
                        return true;
                        }

                    /////////////////////////////// ABOUT ///////////////////////////////

                    if (strtolower($args[0] == 'about')) {
                        $sender->sendMessage(TextFormat::GREEN . "§l§b»§r\n eThis Server Using A Guilds System.\n eBe The Most Powerfull Guilds In This Server!\n eCreate,Join,Destroy A Guilds!\n eStart Now By Using This Commands! : /guilds help [page]\n§l§b« ");
                        $sender->sendMessage(TextFormat::GOLD . "\n\naDevelop By GamerXzavier.");
                    }
                }
            }
        } else {
            $this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Please run command in game"));
        }
    }

}
