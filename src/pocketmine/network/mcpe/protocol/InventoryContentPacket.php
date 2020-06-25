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

use pocketmine\item\Item;
use pocketmine\network\mcpe\NetworkSession;
use function count;

class InventoryContentPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::INVENTORY_CONTENT_PACKET;

	/** @var int */
	public $windowId;
	/** @var Item[] */
	public $items = [];

	protected function decodePayload(int $protocolId){
		$this->windowId = $this->getUnsignedVarInt();
		$count = $this->getUnsignedVarInt();
		for($i = 0; $i < $count; ++$i){
			if($protocolId >= ProtocolInfo::PROTOCOL_1_16_0){
				$this->getVarInt();
			}
			$this->items[] = $this->getSlot();
		}
	}

	protected function encodePayload(int $protocolId){
		$this->putUnsignedVarInt($this->windowId);
		$this->putUnsignedVarInt(count($this->items));
		foreach($this->items as $item){
			if($protocolId >= ProtocolInfo::PROTOCOL_1_16_0){
				$this->putVarInt($item->isNull() ? 0 : 1);
			}
			$this->putSlot($item);
		}
	}

	public function getProtocolVersions() : array{
		return [ProtocolInfo::PROTOCOL_1_16_0, ProtocolInfo::PROTOCOL_1_14_0];
	}

	public function handle(NetworkSession $session) : bool{
		return $session->handleInventoryContent($this);
	}
}
