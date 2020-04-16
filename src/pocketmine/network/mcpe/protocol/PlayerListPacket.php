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
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use function count;

class PlayerListPacket extends DataPacket{
	public const NETWORK_ID = ProtocolInfo::PLAYER_LIST_PACKET;

	public const TYPE_ADD = 0;
	public const TYPE_REMOVE = 1;

	/** @var PlayerListEntry[] */
	public $entries = [];
	/** @var int */
	public $type;

	public function clean(){
		$this->entries = [];
		return parent::clean();
	}

	protected function decodePayload(int $protocolId){
		$this->type = $this->getByte();
		$count = $this->getUnsignedVarInt();
		for($i = 0; $i < $count; ++$i){
			$entry = new PlayerListEntry();
            $entry->uuid = $this->getUUID();
			if($this->type === self::TYPE_ADD){
				$entry->entityUniqueId = $this->getEntityUniqueId();
				$entry->username = $this->getString();
				$entry->xboxUserId = $this->getString();
				$entry->platformChatId = $this->getString();
				$entry->buildPlatform = $this->getLInt();
				$entry->skinData = $this->getSkin($protocolId);
				$entry->isTeacher = $this->getBool();
				$entry->isHost = $this->getBool();
			}

			$this->entries[$i] = $entry;
		}

        if($protocolId === ProtocolInfo::PROTOCOL_1_14_60) {
            for($i = 0; $i < $count; ++$i){
                if(!$this->feof()){
                    $this->getBool(); // isTrusted
                }
            }
        }
	}

	protected function encodePayload(int $protocolId){
		$this->putByte($this->type);
		$this->putUnsignedVarInt(count($this->entries));
		foreach($this->entries as $entry){
			if($this->type === self::TYPE_ADD){
				$this->putUUID($entry->uuid);
				$this->putEntityUniqueId($entry->entityUniqueId);
				$this->putString($entry->username);
				$this->putString($entry->xboxUserId);
				$this->putString($entry->platformChatId);
				$this->putLInt($entry->buildPlatform);
				$this->putSkin($entry->skinData, $protocolId);
				$this->putBool($entry->isTeacher);
				$this->putBool($entry->isHost);
			}else{
				$this->putUUID($entry->uuid);
			}
		}

        if($protocolId === ProtocolInfo::PROTOCOL_1_14_60  && $this->type === self::TYPE_ADD){
            foreach ($this->entries as $entry){
                $this->putBool(true);
            }
        }
	}

    public function getProtocolVersions(): array{
        return [ProtocolInfo::CURRENT_PROTOCOL, ProtocolInfo::PROTOCOL_1_14_0];
    }

	public function handle(NetworkSession $session) : bool{
		return $session->handlePlayerList($this);
	}
}
