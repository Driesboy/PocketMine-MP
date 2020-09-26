<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
*/

declare(strict_types=1);

namespace pocketmine\network\mcpe\protocol;

#include <rules/DataPacket.h>

use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\types\ExperimentData;
use pocketmine\resourcepacks\ResourcePack;
use function count;

class ResourcePackStackPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::RESOURCE_PACK_STACK_PACKET;

	/** @var bool */
	public $mustAccept = false;

	/** @var ResourcePack[] */
	public $behaviorPackStack = [];
	/** @var ResourcePack[] */
	public $resourcePackStack = [];

	/** @var ExperimentData[] */
	private $experiments = [];
	/** @var bool */
	public $isExperimental = false;
	/** @var string */
	public $baseGameVersion = ProtocolInfo::MINECRAFT_VERSION_NETWORK;

	protected function decodePayload(int $protocolId){
		$this->mustAccept = $this->getBool();
		$behaviorPackCount = $this->getUnsignedVarInt();
		while($behaviorPackCount-- > 0){
			$this->getString();
			$this->getString();
			$this->getString();
		}

		$resourcePackCount = $this->getUnsignedVarInt();
		while($resourcePackCount-- > 0){
			$this->getString();
			$this->getString();
			$this->getString();
		}

		if($protocolId < ProtocolInfo::PROTOCOL_1_16_100){
			$this->isExperimental = $this->getBool();
		}

		$this->baseGameVersion = $this->getString();
		if($protocolId >= ProtocolInfo::PROTOCOL_1_16_100){
			$this->experiments = $this->getExperiments();
			$this->isExperimental = $this->getBool();
		}
	}

	protected function encodePayload(int $protocolId){
		$this->putBool($this->mustAccept);

		$this->putUnsignedVarInt(count($this->behaviorPackStack));
		foreach($this->behaviorPackStack as $entry){
			$this->putString($entry->getPackId());
			$this->putString($entry->getPackVersion());
			$this->putString(""); //TODO: subpack name
		}

		$this->putUnsignedVarInt(count($this->resourcePackStack));
		foreach($this->resourcePackStack as $entry){
			$this->putString($entry->getPackId());
			$this->putString($entry->getPackVersion());
			$this->putString(""); //TODO: subpack name
		}

		if($protocolId < ProtocolInfo::PROTOCOL_1_16_100){
			$this->putBool($this->isExperimental);
		}
		$this->putString($this->baseGameVersion);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_16_100){
			$this->putExperiments($this->experiments);
			$this->putBool($this->isExperimental);
		}
	}

	public function getProtocolVersions() : array{
		return [ProtocolInfo::PROTOCOL_1_16_100, ProtocolInfo::PROTOCOL_1_14_0];
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleResourcePackStack($this);
	}
}
