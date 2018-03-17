<?php

namespace Johnmacrocraft\NotMeXboxLiveAuth;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\lang\TranslationContainer;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class NotMeXboxLiveAuth extends PluginBase implements Listener {

	/** @var Config */
	public $xboxlist;

	public function onEnable() {
		if(!$this->getServer()->requiresAuthentication()) {
			$this->getServer()->getLogger()->warning("To use NotMeXboxLiveAuth, you must enable online mode in server.properties. Set value of xbox-auth to true to enable online mode.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		@mkdir($this->getDataFolder());
		$this->xboxlist = new Config($this->getDataFolder() . "xbox-list.txt", Config::ENUM);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	/**
	 * @param CommandSender $sender
	 * @param Command $command
	 * @param string $label
	 * @param string[] $args
	 *
	 * @return bool
	 */
	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		switch($command->getName()) {
			case "xboxlist":
				if(count($args) === 0 || count($args) > 2) {
					throw new InvalidCommandSyntaxException();
				}

				if(count($args) === 1) {
					if($this->badPerm($sender, strtolower($args[0]))) {
						return true;
					}
					switch(strtolower($args[0])) {
						case "add":
							$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist add <player>"]));
							return true;

						case "remove":
							$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist remove <player>"]));
							return true;

						case "list":
							$entries = $this->xboxlist->getAll(true);
							$result = implode($entries, ", ");
							$count = count($entries);
							$sender->sendMessage(TextFormat::AQUA . "There are " . $count . " xboxlisted players:");
							$sender->sendMessage($result);
							return true;

						case "reload":
							$this->reloadXboxlist();
							$sender->sendMessage(TextFormat::GREEN . "Reloaded the xboxlist");
							return true;

						default:
							throw new InvalidCommandSyntaxException();
					}
				} elseif(count($args) === 2) {
					if($this->badPerm($sender, strtolower($args[0]))) {
						return true;
					}
					switch(strtolower($args[0])) {
						case "add":
							$this->addXboxlist($args[1]);
							$sender->sendMessage(TextFormat::GREEN . "Added " . $args[1] . " to the xboxlist");
							return true;

						case "remove":
							$this->removeXboxlist($args[1]);
							$sender->sendMessage(TextFormat::GREEN . "Removed " . $args[1] . " from the xboxlist");
							return true;
					}
				}
		}
		return true;
	}

	private function badPerm(CommandSender $sender, string $perm) : bool {
		if(!$sender->hasPermission("notmexboxliveauth.command.xboxlist.$perm")) {
			$sender->sendMessage(new TranslationContainer(TextFormat::RED . "%commands.generic.permission"));
			return true;
		}
		return false;
	}

	/**
	 * @param PlayerKickEvent $event
	 */
	public function onPlayerKick(PlayerKickEvent $event) {
		if($event->getReason() === "disconnectionScreen.notAuthenticated" && $this->xboxlist->exists(strtolower($event->getPlayer()->getName()))) {
			$event->setCancelled();
		}
	}

	/**
	 * @param string $name
	 */
	public function addXboxlist(string $name) {
		$this->xboxlist->set(strtolower($name), true);
		$this->xboxlist->save(true);
	}

	/**
	 * @param string $name
	 */
	public function removeXboxlist(string $name) {
		$this->xboxlist->remove(strtolower($name));
		$this->xboxlist->save();
	}

	public function reloadXboxlist() {
		$this->xboxlist->reload();
	}
}