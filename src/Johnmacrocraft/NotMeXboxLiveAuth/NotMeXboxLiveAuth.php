<?php

/*
 *
 * NotMeXboxLiveAuth
 *
 * Copyright (C) 2017-2021 Johnmacro
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 */

declare(strict_types=1);

namespace Johnmacrocraft\NotMeXboxLiveAuth;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\lang\TranslationContainer;
use pocketmine\Player;
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

		$invert = $this->useInvert();
		if($this->getServer()->requiresAuthentication() === $invert) {
			$this->getLogger()->warning(
				"To use NotMeXboxLiveAuth, you must " .
				($invert ? "disable (invert mode enabled)" : "enable (invert mode disabled)") .
				" online mode. To " . ($invert ? "disable" : "enable") . " online mode, set \"xbox-auth\" to \"" .
				($invert ? "false" : "true") . "\" in server.properties."
			);
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->xboxlist = new Config($this->getDataFolder() . "xbox-list.txt", Config::ENUM);
		$this->prefixes = new Config($this->getDataFolder() . "prefixes.txt", Config::ENUM);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
		switch($command->getName()) {
			case "xboxlist":
				if(count($args) === 1) {
					if($this->badPerm($sender, strtolower($args[0]))) {
						return true;
					}
					switch(strtolower($args[0])) {
						case "add":
						case "remove":
							$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist " . strtolower($args[0]) . " <player>"]));
							return true;

						case "list":
							$entries = $this->xboxlist->getAll(true);
							$sender->sendMessage(TextFormat::AQUA . "There are " . count($entries) . " xboxlisted players:");
							$sender->sendMessage(implode(", ", $entries));
							return true;

						case "invert":
							$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist invert <bool|state>"]));
							return true;

						case "prefix":
							$sender->sendMessage(new TranslationContainer("commands.generic.usage", ["/xboxlist prefix <add|remove|list|reload> [prefix]"]));
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
					if(!Player::isValidUserName($args[1])) {
						throw new InvalidCommandSyntaxException();
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
							$invert = filter_var($args[1], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
							if(is_bool($invert)) {
								$this->setInvert($invert);
								$sender->sendMessage(TextFormat::GREEN . ($invert ? "Enabled" : "Disabled") . " invert mode; please " . ($invert ? "disable" : "enable") . " online mode.");
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
									$prefixes = $this->prefixes->getAll(true);
									$sender->sendMessage(TextFormat::AQUA . "There are " . count($prefixes) . " guest prefixes:");
									$sender->sendMessage(implode(", ", $prefixes));
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
					if(!Player::isValidUserName($args[2])) {
						throw new InvalidCommandSyntaxException();
					}
					switch(strtolower($args[0])) {
						case "prefix":
							if($this->useInvert()) {
								$sender->sendMessage(TextFormat::YELLOW . "Please disable invert mode before using guest prefixes");
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
		throw new InvalidCommandSyntaxException();
	}

	private function badPerm(CommandSender $sender, string $perm) : bool {
		if(!$sender->hasPermission("notmexboxliveauth.command.xboxlist.$perm")) {
			$sender->sendMessage($sender->getServer()->getLanguage()->translateString(TextFormat::RED . "%commands.generic.permission"));
			return true;
		}
		return false;
	}

	/**
	 * @priority LOWEST
	 */
	public function onPlayerKick(PlayerKickEvent $event) : void {
		$name = $event->getPlayer()->getLowerCaseName();
		if(
			($event->getReason() === "disconnectionScreen.notAuthenticated" && !$this->useInvert()) &&
			($this->xboxlist->exists($name) || $this->startsWithPrefix($name))
		) {
			$event->setCancelled();
		}
	}

	/**
	 * @priority HIGHEST
	 */
	public function onPlayerLogin(PlayerLoginEvent $event) : void {
		if(!$event->getPlayer()->isAuthenticated() && $this->useInvert() && $this->xboxlist->exists($event->getPlayer()->getLowerCaseName())) {
			$event->setKickMessage("disconnectionScreen.notAuthenticated");
			$event->setCancelled();
		}
	}

	public function addXboxlist(string $name) : void {
		$this->xboxlist->set(strtolower($name), true);
		$this->xboxlist->save();
	}

	public function removeXboxlist(string $name) : void {
		$this->xboxlist->remove(strtolower($name));
		$this->xboxlist->save();
	}

	public function reloadXboxlist() : void {
		$this->xboxlist->reload();
	}

	public function setInvert(bool $value) : void {
		$this->getConfig()->set("invert", $value);
		$this->getConfig()->save();
	}

	public function useInvert() : bool {
		return $this->getConfig()->get("invert");
	}

	public function addPrefix(string $prefix) : void {
		$this->prefixes->set(strtolower($prefix), true);
		$this->prefixes->save();
	}

	public function removePrefix(string $prefix) : void {
		$this->prefixes->remove(strtolower($prefix));
		$this->prefixes->save();
	}

	public function reloadPrefixes() : void {
		$this->prefixes->reload();
	}

	public function startsWithPrefix(string $name) : bool {
		foreach($this->prefixes->getAll(true) as $prefixes) {
			if(strpos(strtolower($name), $prefixes) === 0) {
				return true;
			}
		}
		return false;
	}
}
