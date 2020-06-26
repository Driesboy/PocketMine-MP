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

namespace pocketmine\network\mcpe\protocol\types;

use pocketmine\network\mcpe\NetworkBinaryStream;
<<<<<<< HEAD
use pocketmine\network\mcpe\protocol\ProtocolInfo;
=======
>>>>>>> upstream/stable

final class SpawnSettings{
	public const BIOME_TYPE_DEFAULT = 0;
	public const BIOME_TYPE_USER_DEFINED = 1;

	/** @var int */
	private $biomeType;
	/** @var string */
	private $biomeName;
	/** @var int */
	private $dimension;

	public function __construct(int $biomeType, string $biomeName, int $dimension){
		$this->biomeType = $biomeType;
		$this->biomeName = $biomeName;
		$this->dimension = $dimension;
	}

	public function getBiomeType() : int{
		return $this->biomeType;
	}

	public function getBiomeName() : string{
		return $this->biomeName;
	}

	/**
	 * @see DimensionIds
	 */
	public function getDimension() : int{
		return $this->dimension;
	}

<<<<<<< HEAD
	public static function read(NetworkBinaryStream $in, int $protocolId) : self{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_16_0){
			$biomeType = $in->getLShort();
			$biomeName = $in->getString();
		}else{
			$biomeType = self::BIOME_TYPE_DEFAULT;
			$biomeName = '';
		}
=======
	public static function read(NetworkBinaryStream $in) : self{
		$biomeType = $in->getLShort();
		$biomeName = $in->getString();
>>>>>>> upstream/stable
		$dimension = $in->getVarInt();

		return new self($biomeType, $biomeName, $dimension);
	}

<<<<<<< HEAD
	public function write(NetworkBinaryStream $out, int $protocolId) : void{
		if($protocolId >= ProtocolInfo::PROTOCOL_1_16_0){
			$out->putLShort($this->biomeType);
			$out->putString($this->biomeName);
		}
=======
	public function write(NetworkBinaryStream $out) : void{
		$out->putLShort($this->biomeType);
		$out->putString($this->biomeName);
>>>>>>> upstream/stable
		$out->putVarInt($this->dimension);
	}
}
