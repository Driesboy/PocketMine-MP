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

namespace pocketmine\inventory;

use pocketmine\item\Item;
use pocketmine\network\mcpe\protocol\BatchPacket;
use pocketmine\network\mcpe\protocol\CraftingDataPacket;
use pocketmine\network\mcpe\protocol\ProtocolInfo;
use pocketmine\Server;
use pocketmine\timings\Timings;
use pocketmine\utils\AssumptionFailedError;
use function array_map;
use function file_get_contents;
use function is_array;
use function json_decode;
use function json_encode;
use function usort;
use const DIRECTORY_SEPARATOR;

class CraftingManager{
	/** @var ShapedRecipe[][] */
	protected $shapedRecipes = [];
	/** @var ShapelessRecipe[][] */
	protected $shapelessRecipes = [];
	/** @var FurnaceRecipe[] */
	protected $furnaceRecipes = [];

	/** @var BatchPacket[] */
	private $craftingDataCache = [];

	public function __construct(){
		$this->init();
	}

	public function init() : void{

		$itemDeserializerFunc = \Closure::fromCallable([Item::class, 'jsonDeserialize']);

		// 1.16 :)
		$protocolId = ProtocolInfo::PROTOCOL_1_16_0;
		$recipes = json_decode(file_get_contents(\pocketmine\RESOURCE_PATH . "vanilla" . DIRECTORY_SEPARATOR . "recipes.json"), true);

		foreach($recipes["shapeless"] as $recipe){
			if($recipe["block"] !== "crafting_table"){ //TODO: filter others out for now to avoid breaking economics
				continue;
			}

			$this->registerShapelessRecipe($protocolId, new ShapelessRecipe(
				array_map($itemDeserializerFunc, $recipe["input"]),
				array_map($itemDeserializerFunc, $recipe["output"])
			));
		}
		foreach($recipes["shaped"] as $recipe){
			if($recipe["block"] !== "crafting_table"){ //TODO: filter others out for now to avoid breaking economics
				continue;
			}

			$this->registerShapedRecipe($protocolId, new ShapedRecipe(
				$recipe["shape"],
				array_map($itemDeserializerFunc, $recipe["input"]),
				array_map($itemDeserializerFunc, $recipe["output"])
			));
		}
		foreach($recipes["smelting"] as $recipe){
			if($recipe["block"] !== "furnace"){ //TODO: filter others out for now to avoid breaking economics
				continue;
			}

			$this->registerFurnaceRecipe($protocolId, new FurnaceRecipe(
				Item::jsonDeserialize($recipe["output"]),
				Item::jsonDeserialize($recipe["input"]))
			);
		}

		$this->buildCraftingDataCache($protocolId);

		$protocolId = ProtocolInfo::PROTOCOL_1_14_0;
		$recipes = json_decode(file_get_contents(\pocketmine\RESOURCE_PATH . "recipes-1.14.json"), true);

		foreach($recipes as $recipe){
			switch($recipe["type"]){
				case "shapeless":
					if($recipe["block"] !== "crafting_table"){ //TODO: filter others out for now to avoid breaking economics
						break;
					}
					$this->registerShapelessRecipe($protocolId, new ShapelessRecipe(
						array_map($itemDeserializerFunc, $recipe["input"]),
						array_map($itemDeserializerFunc, $recipe["output"])
					));
					break;
				case "shaped":
					if($recipe["block"] !== "crafting_table"){ //TODO: filter others out for now to avoid breaking economics
						break;
					}
					$this->registerShapedRecipe($protocolId, new ShapedRecipe(
						$recipe["shape"],
						array_map($itemDeserializerFunc, $recipe["input"]),
						array_map($itemDeserializerFunc, $recipe["output"])
					));
					break;
				case "smelting":
					if($recipe["block"] !== "furnace"){ //TODO: filter others out for now to avoid breaking economics
						break;
					}
					$this->registerFurnaceRecipe($protocolId, new FurnaceRecipe(
						Item::jsonDeserialize($recipe["output"]),
						Item::jsonDeserialize($recipe["input"]))
					);
					break;
				default:
					break;
			}
		}

		$this->buildCraftingDataCache($protocolId);
	}

	/**
	 * Rebuilds the cached CraftingDataPacket.
	 */
	public function buildCraftingDataCache(int $protocolId) : void{
		Timings::$craftingDataCacheRebuildTimer->startTiming();
		$pk = new CraftingDataPacket();
		$pk->cleanRecipes = true;

		foreach($this->shapelessRecipes[$protocolId] as $list){
			foreach($list as $recipe){
				$pk->addShapelessRecipe($recipe);
			}
		}
		foreach($this->shapedRecipes[$protocolId] as $list){
			foreach($list as $recipe){
				$pk->addShapedRecipe($recipe);
			}
		}

		foreach($this->furnaceRecipes[$protocolId] as $recipe){
			$pk->addFurnaceRecipe($recipe);
		}

		$batch = new BatchPacket();
		$batch->setCompressionLevel(Server::getInstance()->networkCompressionLevel);
		$batch->addPacket($pk, $protocolId);
		$batch->encode($protocolId);

		$this->craftingDataCache[$protocolId] = $batch;

		Timings::$craftingDataCacheRebuildTimer->stopTiming();
	}

	/**
	 * Returns a pre-compressed CraftingDataPacket for sending to players. Rebuilds the cache if it is not found.
	 */
	public function getCraftingDataPacket(int $protocolId) : BatchPacket{
		if(empty($this->craftingDataCache[$protocolId])){
			$this->buildCraftingDataCache($protocolId);
		}

		return $this->craftingDataCache[$protocolId];
	}

	/**
	 * Function used to arrange Shapeless Recipe ingredient lists into a consistent order.
	 *
	 * @return int
	 */
	public static function sort(Item $i1, Item $i2){
		//Use spaceship operator to compare each property, then try the next one if they are equivalent.
		($retval = $i1->getId() <=> $i2->getId()) === 0 && ($retval = $i1->getDamage() <=> $i2->getDamage()) === 0 && ($retval = $i1->getCount() <=> $i2->getCount()) === 0;

		return $retval;
	}

	/**
	 * @param Item[] $items
	 *
	 * @return Item[]
	 */
	private static function pack(array $items) : array{
		/** @var Item[] $result */
		$result = [];

		foreach($items as $i => $item){
			foreach($result as $otherItem){
				if($item->equals($otherItem)){
					$otherItem->setCount($otherItem->getCount() + $item->getCount());
					continue 2;
				}
			}

			//No matching item found
			$result[] = clone $item;
		}

		return $result;
	}

	/**
	 * @param Item[] $outputs
	 */
	private static function hashOutputs(array $outputs) : string{
		$outputs = self::pack($outputs);
		usort($outputs, [self::class, "sort"]);
		foreach($outputs as $o){
			//this reduces accuracy of hash, but it's necessary to deal with recipe book shift-clicking stupidity
			$o->setCount(1);
		}

		return json_encode($outputs);
	}

	/**
	 * @return ShapelessRecipe[][]
	 */
	public function getShapelessRecipes() : array{
		return $this->shapelessRecipes;
	}

	/**
	 * @return ShapedRecipe[][]
	 */
	public function getShapedRecipes() : array{
		return $this->shapedRecipes;
	}

	/**
	 * @return FurnaceRecipe[]
	 */
	public function getFurnaceRecipes() : array{
		return $this->furnaceRecipes;
	}

	public function registerShapedRecipe(int $protocolId, ShapedRecipe $recipe) : void{
		$this->shapedRecipes[$protocolId][self::hashOutputs($recipe->getResults())][] = $recipe;

		$this->craftingDataCache[$protocolId] = [];
	}

	public function registerShapelessRecipe(int $protocolId, ShapelessRecipe $recipe) : void{
		$this->shapelessRecipes[$protocolId][self::hashOutputs($recipe->getResults())][] = $recipe;

		$this->craftingDataCache[$protocolId] = [];
	}

	public function registerFurnaceRecipe(int $protocolId, FurnaceRecipe $recipe) : void{
		$input = $recipe->getInput();
		$this->furnaceRecipes[$protocolId][$input->getId() . ":" . ($input->hasAnyDamageValue() ? "?" : $input->getDamage())] = $recipe;
		$this->craftingDataCache[$protocolId] = [];
	}

	/**
	 * @param Item[]       $outputs
	 */
	public function matchRecipe(int $protocolId, CraftingGrid $grid, array $outputs) : ?CraftingRecipe{
		//TODO: try to match special recipes before anything else (first they need to be implemented!)

		$outputHash = self::hashOutputs($outputs);

		if(isset($this->shapedRecipes[$protocolId][$outputHash])){
			foreach($this->shapedRecipes[$protocolId][$outputHash] as $recipe){
				if($recipe->matchesCraftingGrid($grid)){
					return $recipe;
				}
			}
		}

		if(isset($this->shapelessRecipes[$outputHash])){
			foreach($this->shapelessRecipes[$protocolId][$outputHash] as $recipe){
				if($recipe->matchesCraftingGrid($grid)){
					return $recipe;
				}
			}
		}

		return null;
	}

	/**
	 * @param Item[] $outputs
	 *
	 * @return CraftingRecipe[]|\Generator
	 * @phpstan-return \Generator<int, CraftingRecipe, void, void>
	 */
	public function matchRecipeByOutputs(int $protocolId, array $outputs) : \Generator{
		//TODO: try to match special recipes before anything else (first they need to be implemented!)

		$outputHash = self::hashOutputs($outputs);

		if(isset($this->shapedRecipes[$protocolId][$outputHash])){
			foreach($this->shapedRecipes[$protocolId][$outputHash] as $recipe){
				yield $recipe;
			}
		}

		if(isset($this->shapelessRecipes[$protocolId][$outputHash])){
			foreach($this->shapelessRecipes[$protocolId][$outputHash] as $recipe){
				yield $recipe;
			}
		}
	}

	public function matchFurnaceRecipe(Item $input) : ?FurnaceRecipe{
		return $this->furnaceRecipes[ProtocolInfo::PROTOCOL_1_16_0][$input->getId() . ":" . $input->getDamage()] ?? $this->furnaceRecipes[ProtocolInfo::PROTOCOL_1_16_0][$input->getId() . ":?"] ?? null;
	}

	/**
	 * @deprecated
	 */
	public function registerRecipe(Recipe $recipe) : void{
		$recipe->registerToCraftingManager($this);
	}
}
