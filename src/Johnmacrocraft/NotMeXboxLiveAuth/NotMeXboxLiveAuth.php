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
	/** @var Config */
	public $prefixes;

	public function onEnable() : void {
		if(!is_dir($this->getDataFolder())) {
			mkdir($this->getDataFolder());
		}
		if(!file_exists($this->getDataFolder() . "config.yml")) {
			$this->saveDefaultConfig();
		}
		if($this->getServer()->requiresAuthentication() === $invert = $this->useInvert()) {
			$this->getLogger()->warning("To use NotMeXboxLiveAuth, you must " .
				($invert ? "disable (invert mode enabled)" : "enable (invert mode disabled)") .
				" online mode in server.properties. Set value of xbox-auth to " .
				($invert ? "false" : "true") . " to " . ($invert ? "disable" : "enable") . " online mode."
			);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}
		$this->xboxlist = new Config($this->getDataFolder() . "xbox-list.txt", Config::ENUM);
		$this->prefixes = new Config($this->getDataFolder() . "prefixes.txt", Config::ENUM);
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
				if(count($args) === 0 || count($args) > 3) {
					throw new InvalidCommandSyntaxException();
				}

				if(count($args) === 1) {
					if($this->badPerm($sender, strtolower($args[0]))) {
						return true;
					}
					switch(strtolower($args[0])) {
						case "add":
						case "remove":
							$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist " . strtolower($args[0]) . " <player>"]));
							return true;

						case "invert":
							$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist invert <bool|state>"]));
							return true;

						case "prefix":
							$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist prefix <add|remove|list|reload> [prefix]"]));
							return true;

						case "list":
							$sender->sendMessage(TextFormat::AQUA . "There are " . count($entries = $this->xboxlist->getAll(true)) . " xboxlisted players:");
							$sender->sendMessage(implode($entries, ", "));
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
							$sender->sendMessage(TextFormat::GREEN . "Added $args[1] to the xboxlist");
							return true;

						case "remove":
							$this->removeXboxlist($args[1]);
							$sender->sendMessage(TextFormat::GREEN . "Removed $args[1] from the xboxlist");
							return true;

						case "invert":
							if(is_bool($invert = filter_var($args[1], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE))) {
								$this->setInvert($invert);
								$sender->sendMessage(TextFormat::GREEN . ($invert ? "Enabled" : "Disabled") . " invert mode - please " . ($invert ? "disable" : "enable") . " online mode.");
							} elseif($args[1] === "state") {
								$sender->sendMessage(TextFormat::AQUA . "Invert mode is currently " . ($this->useInvert() ? "enabled" : "disabled") . ".");
							} else {
								$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist invert <bool|state>"]));
							}
							return true;

						case "prefix":
							switch(strtolower($args[1])) {
								case "add":
								case "remove":
									$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist prefix " . strtolower($args[1]) . " <prefix>"]));
									return true;

								case "list":
									$sender->sendMessage(TextFormat::AQUA . "There are " . count($prefixes = $this->prefixes->getAll(true)) . " guest prefixes:");
									$sender->sendMessage(implode($prefixes, ", "));
									return true;

								case "reload":
									$this->reloadPrefixes();
									$sender->sendMessage(TextFormat::GREEN . "Reloaded the guest prefix list");
									return true;

								default:
									$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist prefix <add|remove|list> [prefix]"]));
									return true;
							}
					}
				} elseif(count($args) === 3) {
					if($this->badPerm($sender, strtolower($args[0]))) {
						return true;
					}
					switch(strtolower($args[0])) {
						case "prefix":
							if($this->useInvert()) {
								$sender->sendMessage(TextFormat::YELLOW . "Please disable invert mode before trying to use guest prefix");
							}
							switch(strtolower($args[1])) {
								case "add":
									$this->addPrefix($args[2]);
									$sender->sendMessage(TextFormat::GREEN . "Added $args[2] to the guest prefix list");
									return true;

								case "remove":
									$this->removePrefix($args[2]);
									$sender->sendMessage(TextFormat::GREEN . "Removed $args[2] from the guest prefix list");
									return true;
							}
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
	 * @priority HIGHEST
	 */
	public function onPlayerKick(PlayerKickEvent $event) : void {
		if(($event->getReason() === "disconnectionScreen.notAuthenticated" && !$this->useInvert()) && ($this->xboxlist->exists($name = $event->getPlayer()->getLowerCaseName()) || $this->startsWithPrefix($name))) {
			$event->setCancelled();
		}
	}

	/**
	 * @param PlayerJoinEvent $event
	 * @priority HIGHEST
	 */
	public function onPlayerJoin(PlayerJoinEvent $event) : void {
		if(!$event->getPlayer()->isAuthenticated() && $this->useInvert() && $this->xboxlist->exists($event->getPlayer()->getLowerCaseName())) {
			$event->getPlayer()->kick("disconnectionScreen.notAuthenticated", false);
		}
	}

	/**
	 * @param string $name
	 */
	public function addXboxlist(string $name) : void {
		$this->xboxlist->set(strtolower($name), true);
		$this->xboxlist->save();
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

	/**
	 * @param bool $value
	 */
	public function setInvert(bool $value) : void {
		$this->getConfig()->set("invert", $value);
		$this->getConfig()->save();
	}

	/**
	 * @return bool
	 */
	public function useInvert() : bool {
		return $this->getConfig()->get("invert");
	}

	/**
	 * @param string $prefix
	 */
	public function addPrefix(string $prefix) : void {
		$this->prefixes->set(strtolower($prefix), true);
		$this->prefixes->save();
	}

	/**
	 * @param string $prefix
	 */
	public function removePrefix(string $prefix) : void {
		$this->prefixes->remove(strtolower($prefix));
		$this->prefixes->save();
	}

	public function reloadPrefixes() : void {
		$this->prefixes->reload();
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function startsWithPrefix(string $name) : bool {
		foreach($this->prefixes->getAll(true) as $prefixes) {
			if(strpos($name, $prefixes) === 0) {
				return true;
			}
		}
		return false;
	}
}