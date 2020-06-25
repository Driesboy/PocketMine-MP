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
use pocketmine\network\mcpe\protocol\types\DimensionIds;

class SetSpawnPositionPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::SET_SPAWN_POSITION_PACKET;

	public const TYPE_PLAYER_SPAWN = 0;
	public const TYPE_WORLD_SPAWN = 1;

	/** @var int */
	public $spawnType;
	/** @var int */
	public $x;
	/** @var int */
	public $y;
	/** @var int */
	public $z;
	/** @var bool */
	public $spawnForced;
	/** @var int */
	public $dimensionType = DimensionIds::OVERWORLD;

	protected function decodePayload(int $protocolId){
		$this->spawnType = $this->getVarInt();
		$this->getBlockPosition($this->x, $this->y, $this->z);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_16_0){
			$this->dimensionType = $this->getVarInt();
			$this->getBlockPosition($this->x, $this->y, $this->z);
		}else{
			$this->spawnForced = $this->getBool();
		}
	}

	protected function encodePayload(int $protocolId){
		$this->putVarInt($this->spawnType);
		$this->putBlockPosition($this->x, $this->y, $this->z);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_16_0){
			$this->putVarInt($this->dimensionType);
			$this->putBlockPosition($this->x, $this->y, $this->z);
		}else{
			$this->putBool($this->spawnForced);
		}
	}

	public function getProtocolVersions() : array{
		return [ProtocolInfo::PROTOCOL_1_16_0, ProtocolInfo::PROTOCOL_1_14_0];
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleSetSpawnPosition($this);
	}
}
