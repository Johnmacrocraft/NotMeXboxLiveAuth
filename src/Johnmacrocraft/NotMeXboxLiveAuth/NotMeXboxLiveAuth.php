<?php

/*
 *
 * NotMeXboxLiveAuth
 *
 * Copyright © 2017-2018 Johnmacrocraft
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

namespace Johnmacrocraft\NotMeXboxLiveAuth;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\lang\TranslationContainer;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class NotMeXboxLiveAuth extends PluginBase implements Listener {

	/** @var Config */
	public $xboxlist;

	public function onEnable() : void {
		if(!is_dir($this->getDataFolder())) {
			mkdir($this->getDataFolder());
		}
		if(!file_exists($this->getDataFolder() . "config.yml")) {
			$this->saveDefaultConfig();
		}
		if($this->getServer()->requiresAuthentication() === $invert = $this->getConfig()->get("invert")) {
			$this->getServer()->getLogger()->warning("To use NotMeXboxLiveAuth, you must " . ($invert ? "disable (invert mode enabled)" : "enable (invert mode disabled)") . " online mode in server.properties. Set value of xbox-auth to " . ($invert ? "false" : "true") . " to " . ($invert ? "disable" : "enable") . " online mode.");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
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

						case "invert":
							$sender->sendMessage(TextFormat::AQUA . "Invert mode is currently " . ($this->getConfig()->get("invert") ? "enabled" : "disabled") . ".");
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

						case "invert":
							if(is_bool($invert = filter_var($args[1], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
								$this->getConfig()->set("invert", $invert);
								$this->getConfig()->save();
								$sender->sendMessage(TextFormat::GREEN . ($invert ? "Enabled" : "Disabled") . " invert mode - please " . ($invert ? "disable" : "enable") . " online mode.");
							} else {
								$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist invert [bool]"]));
							}
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
	public function onPlayerKick(PlayerKickEvent $event) : void {
		if($event->getReason() === "disconnectionScreen.notAuthenticated" && !$this->getConfig()->get("invert") && $this->xboxlist->exists(strtolower($event->getPlayer()->getName()))) {
			$event->setCancelled();
		}
	}

	/**
	 * @param PlayerJoinEvent $event
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		if(!$event->getPlayer()->isAuthenticated() && $this->getConfig()->get("invert") && $this->xboxlist->exists(strtolower($event->getPlayer()->getName()))) {
			$event->getPlayer()->kick("disconnectionScreen.notAuthenticated");
		}
	}

	/**
	 * @param string $name
	 */
	public function addXboxlist(string $name) : void {
		$this->xboxlist->set(strtolower($name), true);
		$this->xboxlist->save(true);
	}

	/**
	 * @param string $name
	 */
	public function removeXboxlist(string $name) : void {
		$this->xboxlist->remove(strtolower($name));
		$this->xboxlist->save();
	}

	public function reloadXboxlist() : void {
		$this->xboxlist->reload();
	}
}