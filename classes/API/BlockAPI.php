<?php

/*

           -
         /   \
      /         \
   /   PocketMine  \
/          MP         \
|\     @shoghicp     /|
|.   \           /   .|
| ..     \   /     .. |
|    ..    |    ..    |
|       .. | ..       |
\          |          /
   \       |       /
      \    |    /
         \ | /

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.


*/

define("BLOCK_UPDATE_NORMAL", 0);
define("BLOCK_UPDATE_RANDOM", 1);
define("BLOCK_UPDATE_SCHEDULED", 2);
define("BLOCK_UPDATE_WEAK", 3);



class BlockAPI{
	private $server;
	function __construct($server){
		$this->server = $server;
	}
	
	public function init(){
		$this->server->addHandler("world.block.update", array($this, "updateBlockRemote"));
		$this->server->addHandler("player.block.break", array($this, "blockBreak"));
		$this->server->addHandler("player.block.action", array($this, "blockAction"));
	}
	
	public function blockBreak($data, $event){
		if($event !== "player.block.break"){
			return;
		}
		$target = $this->server->api->level->getBlock($data["x"], $data["y"], $data["z"]);
		if(isset(Material::$unbreakable[$target[0]])){
			return;
		}
		$drop = array(
			$target[0], //Block
			$target[1], //Meta
			1, //Count
		);
		switch($target[0]){
			case 16:
				$drop = array(263, 0, 1);
				break;
			case 21:
				$drop = array(351, 4, mt_rand(4, 8));
				break;
			case 56:
				$drop = array(264, 0, 1);
				break;
			case 73:
			case 74:
				$drop = array(351, 4, mt_rand(4, 5));
				break;
			case 18:
				$drop = false;
				if(mt_rand(1,20) === 1){ //Saplings
					$drop = array(6, $target[1], 1);
				}
				if($target[1] === 0 and mt_rand(1,200) === 1){ //Apples
					$this->drop($data["x"], $data["y"], $data["z"], 260, 0, 1);
				}
				break;
			case 59:
				if($target[1] >= 0x07){ //Seeds
					$drop = array(296, 0, 1);
					$this->drop($data["x"], $data["y"], $data["z"], 295, 0, mt_rand(0,3));
				}else{
					$drop = array(295, 0, 1);
				}
				break;
			case 31:
				$drop = false;
				if(mt_rand(1,10) === 1){ //Seeds
					$drop = array(295, 0, 1);
				}
				break;
			case 20:
				$drop = false;
				break;
			case 30:
				$drop = false;
				break;
			case 51:
				$drop = false;
				break;
			case 52:
				$drop = false;
				break;
			case 43:
				$drop = array(
					44,
					$target[1],
					2,
				);
				break;
			case 64: //Door
				$drop = array(324, 0, 1);
				if(($target[1] & 0x08) === 0x08){
					$down = $this->server->api->level->getBlock($data["x"], $data["y"] - 1, $data["z"]);
					if($down[0] === 64){
						$data2 = $data;
						--$data2["y"];
						$this->server->trigger("player.block.break", $data2);
						$this->updateBlocksAround($data2["x"], $data2["y"], $data2["z"], BLOCK_UPDATE_NORMAL);
					}
				}else{
					$up = $this->server->api->level->getBlock($data["x"], $data["y"] + 1, $data["z"]);
					if($up[0] === 64){
						$data2 = $data;
						++$data2["y"];
						$this->server->trigger("player.block.break", $data2);
						$this->updateBlocksAround($data2["x"], $data2["y"], $data2["z"], BLOCK_UPDATE_NORMAL);
					}
				}
				break;
		}
		if($drop !== false and $drop[0] !== 0 and $drop[2] > 0){
			$this->drop($data["x"], $data["y"], $data["z"], $drop[0], $drop[1] & 0x0F, $drop[2] & 0xFF);
		}
		$this->server->trigger("player.block.break", $data);		
		$this->updateBlocksAround($data["x"], $data["y"], $data["z"], BLOCK_UPDATE_NORMAL);
	}
	
	public function drop($x, $y, $z, $block, $meta, $stack = 1){
		if($block === 0 or $stack <= 0 or $this->server->gamemode === 1){
			return;
		}
		$data = array(
			"x" => $x,
			"y" => $y,
			"z" => $z,
			"meta" => $meta,
			"stack" => $stack,
		);
		$data["x"] += mt_rand(2, 8) / 10;
		$data["y"] += mt_rand(2, 8) / 10;
		$data["z"] += mt_rand(2, 8) / 10;
		$e = $this->server->api->entity->add(ENTITY_ITEM, $block, $data);
		$this->server->api->entity->spawnToAll($e->eid);	
	}
	
	public function blockAction($data, $event){
		if($event !== "player.block.action"){
			return;
		}
		if($data["face"] < 0 or $data["face"] > 5){
			return;
		}
		$target = $this->server->api->level->getBlock($data["x"], $data["y"], $data["z"]);
		$cancelPlace = false;
		if(isset(Material::$activable[$target[0]])){			
			switch($target[0]){
				case 2:
				case 3:
				case 6:
					break;
				case 64: //Door
					if(($target[1] & 0x08) === 0x08){
						$down = $this->server->api->level->getBlock($data["x"], $data["y"] - 1, $data["z"]);
						if($down[0] === 64){
							$down[1] = $down[1] ^ 0x04;
							$data2 = array(
								"x" => $data["x"],
								"z" => $data["z"],
								"y" => $data["y"] - 1,
								"block" => $down[0],
								"meta" => $down[1],
								"eid" => $data["eid"],
							);
							$this->server->trigger("player.block.update", $data2);
							$this->updateBlocksAround($data2["x"], $data2["y"], $data2["z"], BLOCK_UPDATE_NORMAL);
							$this->updateBlocksAround($data["x"], $data["y"], $data["z"], BLOCK_UPDATE_NORMAL);
						}
					}else{
						$data["block"] = $target[0];
						$data["meta"] = $target[1] ^ 0x04;
						$this->server->trigger("player.block.update", $data);
						$up = $this->server->api->level->getBlock($data["x"], $data["y"] + 1, $data["z"]);
						if($up[0] === 64){
							$data2 = $data;
							$data2["meta"] = $up[1];
							++$data2["y"];
							$this->updateBlocksAround($data2["x"], $data2["y"], $data2["z"], BLOCK_UPDATE_NORMAL);
						}
						$this->updateBlocksAround($data["x"], $data["y"], $data["z"], BLOCK_UPDATE_NORMAL);
					}
					$cancelPlace = true;
					break;
				case 96: //Trapdoor
				case 107: //Fence gates
					$data["block"] = $target[0];
					$data["meta"] = $target[1] ^ 0x04;
					$this->server->trigger("player.block.update", $data);
					$this->updateBlocksAround($data["x"], $data["y"], $data["z"], BLOCK_UPDATE_NORMAL);
					$cancelPlace = true;
					break;
				default:
					$cancelPlace = true;
					break;
			}			
		}

		if($cancelPlace === true){
			return;
		}
		
		$replace = false;
		switch($target[0]){
			case 44: //Slabs
				if($data["face"] !== 1){
					break;
				}
				if(($target[1] & 0x07) === ($data["meta"] & 0x07)){
					$replace = true;
					$data["block"] = 43;
					$data["meta"] = $data["meta"] & 0x07;
				}
				break;
		}
		
		if($replace === false){
			switch($data["face"]){
				case 0:
					--$data["y"];
					break;
				case 1:
					++$data["y"];
					break;
				case 2:
					--$data["z"];
					break;
				case 3:
					++$data["z"];
					break;
				case 4:
					--$data["x"];
					break;
				case 5:
					++$data["x"];
					break;
			}
		}
			
		if($data["y"] >= 127){
			return;
		}
		
		$block = $this->server->api->level->getBlock($data["x"], $data["y"], $data["z"]);
		
		if($replace === false and !isset(Material::$replaceable[$block[0]])){
			return;
		}
		
		if(isset(Material::$placeable[$data["block"]])){
			$data["block"] = Material::$placeable[$data["block"]] === true ? $data["block"]:Material::$placeable[$data["block"]];
		}else{
			return;
		}
		
		$direction = $this->server->api->entity->get($data["eid"])->getDirection();		
		
		switch($data["block"]){
			case 6:
			case 37:
			case 38:
				if($target[0] !== 2 and $target[0] !== 3){
					return;
				}
				break;
			case 50: //Torch
				if(isset(Material::$transparent[$target[0]])){
					return;
				}
				$faces = array(
					0 => 6,
					1 => 5,
					2 => 4,
					3 => 3,
					4 => 2,
					5 => 1,
				);
				if(!isset($faces[$data["face"]])){
					return false;
				}
				$data["meta"] = $faces[$data["face"]];
				break;
			case 53://Stairs
			case 67:
			case 108:
				$faces = array(
					0 => 0,
					1 => 2,
					2 => 1,
					3 => 3,
				);
				$data["meta"] = $faces[$direction] & 0x03;
				break;
			case 96: //trapdoor
				if(isset(Material::$transparent[$target[0]])){
					return;
				}
				$faces = array(
					2 => 0,
					3 => 1,
					4 => 2,
					5 => 3,
				);
				if(!isset($faces[$data["face"]])){
					return false;
				}
				$data["meta"] = $faces[$data["face"]] & 0x03;
				break;
			case 107: //Fence gate
				$faces = array(
					0 => 3,
					1 => 0,
					2 => 1,
					3 => 2,
				);
				$data["meta"] = $faces[$direction] & 0x03;
				break;
			case 64://Door placing
				$blockUp = $this->server->api->level->getBlock($data["x"], $data["y"] + 1, $data["z"]);
				$blockDown = $this->server->api->level->getBlock($data["x"], $data["y"] - 1, $data["z"]);
				if(!isset(Material::$replaceable[$blockUp[0]]) or isset(Material::$transparent[$blockDown[0]])){
					return;
				}else{
					$data2 = $data;
					$data2["meta"] = 0x08;
					$data["meta"] = $direction & 0x03;
					++$data2["y"];
					$this->server->trigger("player.block.place", $data2);
					$this->updateBlocksAround($data2["x"], $data2["y"], $data2["z"], BLOCK_UPDATE_NORMAL);
				}
				break;
			case 65: //Ladder
				if(isset(Material::$transparent[$target[0]])){
					return;
				}
				$faces = array(
					2 => 2,
					3 => 3,
					4 => 4,
					5 => 5,
				);
				if(!isset($faces[$data["face"]])){
					return false;
				}
				$data["meta"] = $faces[$data["face"]];
				break;
			case 59://Seeds
			case 105:
				$blockDown = $this->server->api->level->getBlock($data["x"], $data["y"] - 1, $data["z"]);
				if($blockDown[0] !== 60){
					return;
				}
				$data["meta"] = 0;
				break;
			case 81: //Cactus
				$blockDown = $this->server->api->level->getBlock($data["x"], $data["y"] - 1, $data["z"]);
				$block0 = $this->server->api->level->getBlock($data["x"], $data["y"], $data["z"] + 1);
				$block1 = $this->server->api->level->getBlock($data["x"], $data["y"], $data["z"] - 1);
				$block2 = $this->server->api->level->getBlock($data["x"] + 1, $data["y"], $data["z"]);
				$block3 = $this->server->api->level->getBlock($data["x"] - 1, $data["y"], $data["z"]);
				if($blockDown[0] !== 12 or $block0[0] !== 0 or $block1[0] !== 0 or $block2[0] !== 0 or $block3[0] !== 0){
					return;
				}
				break;
		}
		$this->server->trigger("player.block.place", $data);
		$this->updateBlock($data["x"], $data["y"], $data["z"], BLOCK_UPDATE_NORMAL);
		$this->updateBlocksAround($data["x"], $data["y"], $data["z"], BLOCK_UPDATE_NORMAL);
	}
	
	public function blockScheduler($data){
		$this->updateBlock($data["x"], $data["y"], $data["z"], BLOCK_UPDATE_SCHEDULED);
	}
	
	public function updateBlockRemote($data, $event){
		if($event !== "world.block.update"){
			return;
		}
		$this->updateBlock($data["x"], $data["y"], $data["z"], isset($data["type"]) ? $data["type"]:BLOCK_UPDATE_RANDOM);
	}
	
	public function updateBlock($x, $y, $z, $type = BLOCK_UPDATE_NORMAL){
		$block = $this->server->api->level->getBlock($x, $y, $z);
		$changed = false;

		switch($block[0]){
			case 8:
			case 9:
				$blockDown = $this->server->api->level->getBlock($x, $y - 1, $z);
				$down = false;
				if(isset(Material::$flowable[$blockDown[0]])){
					if($down[0] !== 9){
						$this->server->schedule(5, array($this, "blockScheduler"), array(
							"x" => $x,
							"y" => $y - 1,
							"z" => $z,
							"type" => BLOCK_UPDATE_NORMAL,
						));
						$this->server->api->level->setBlock($x, $y - 1, $z, 8, 8);
					}
					$down = true;
				}
				if(($block[1] & 0x07) < 7 and ($down === false or $block[0] === 9)){
					$b0 = $this->server->api->level->getBlock($x + 1, $y, $z);
					if(isset(Material::$flowable[$b0[0]]) and $b0[0] !== 9){
						$this->server->schedule(10, array($this, "blockScheduler"), array(
							"x" => $x + 1,
							"y" => $y,
							"z" => $z,
							"type" => BLOCK_UPDATE_NORMAL,
						));
						$this->server->api->level->setBlock($x + 1, $y, $z, 8, ($block[1] + 1) & 0x07);
					}
					$b1 = $this->server->api->level->getBlock($x - 1, $y, $z);
					if(isset(Material::$flowable[$b1[0]]) and $b1[0] !== 9){
						$this->server->schedule(10, array($this, "blockScheduler"), array(
							"x" => $x - 1,
							"y" => $y,
							"z" => $z,
							"type" => BLOCK_UPDATE_NORMAL,
						));
						$this->server->api->level->setBlock($x - 1, $y, $z, 8, ($block[1] + 1) & 0x07);
					}
					$b2 = $this->server->api->level->getBlock($x, $y, $z + 1);
					if(isset(Material::$flowable[$b2[0]]) and $b2[0] !== 9){
						$this->server->schedule(10, array($this, "blockScheduler"), array(
							"x" => $x,
							"y" => $y,
							"z" => $z + 1,
							"type" => BLOCK_UPDATE_NORMAL,
						));
						$this->server->api->level->setBlock($x, $y, $z + 1, 8, ($block[1] + 1) & 0x07);
					}
					$b3 = $this->server->api->level->getBlock($x, $y, $z - 1);
					if(isset(Material::$flowable[$b3[0]]) and $b3[0] !== 9){
						$this->server->schedule(10, array($this, "blockScheduler"), array(
							"x" => $x,
							"y" => $y,
							"z" => $z - 1,
							"type" => BLOCK_UPDATE_NORMAL,
						));
						$this->server->api->level->setBlock($x, $y, $z - 1, 8, ($block[1] + 1) & 0x07);
					}
				}
				break;
			case 74:
				if($type === BLOCK_UPDATE_SCHEDULED or $type === BLOCK_UPDATE_RANDOM){
					$changed = true;
					$this->server->api->level->setBlock($x, $y, $z, 73, $block[1]);
					$type = BLOCK_UPDATE_WEAK;
				}
				break;
			case 73:
				if($type === BLOCK_UPDATE_NORMAL){
					$changed = true;
					$this->server->api->level->setBlock($x, $y, $z, 74, $block[1]);
					$this->server->schedule(mt_rand(40, 100), array($this, "blockScheduler"), array(
						"x" => $x,
						"y" => $y,
						"z" => $z,
					));
					$type = BLOCK_UPDATE_WEAK;
				}
				break;
		}
		if($type === BLOCK_TYPE_SCHEDULED){
			$type = BLOCK_UPDATE_WEAK;
		}
		if($changed === true){
			$this->updateBlocksAround($x, $y, $z, $type);
		}
	}
	
	public function updateBlocksAround($x, $y, $z, $type){
		$this->updateBlock($x + 1, $y, $z, $type);
		$this->updateBlock($x, $y + 1, $z, $type);
		$this->updateBlock($x, $y, $z + 1, $type);
		$this->updateBlock($x - 1, $y, $z, $type);
		$this->updateBlock($x, $y - 1, $z, $type);
		$this->updateBlock($x, $y, $z - 1, $type);
	}
}