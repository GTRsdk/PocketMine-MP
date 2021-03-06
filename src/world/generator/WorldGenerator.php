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

class WorldGenerator{
	private $seed, $level, $path, $random, $generator, $width;
	public function __construct(LevelGenerator $generator, $name, $seed = false, $width = 16, $height = 8){
		$this->seed = $seed !== false ? (int) $seed:Utils::readInt(Utils::getRandomBytes(4, false));
		$this->random = new Random($this->seed);
		$this->width = (int) $width;
		$this->height = (int) $height;
		$this->path = DATA_PATH."worlds/".$name."/";
		$this->generator = $generator;
		$level = new PMFLevel($this->path."level.pmf", array(
			"name" => $name,
			"seed" => $this->seed,
			"time" => 0,
			"spawnX" => 128,
			"spawnY" => 128,
			"spawnZ" => 128,
			"extra" => "",
			"width" => $this->width,
			"height" => $this->height
		));
		$entities = new Config($this->path."entities.yml", CONFIG_YAML);
		$tileEntities = new Config($this->path."tileEntities.yml", CONFIG_YAML);
		$this->level = new Level($level, $entities, $tileEntities, $name);
	}
	
	public function generate(){
		$this->generator->init($this->level, $this->random);
		for($Z = 0; $Z < $this->width; ++$Z){
			for($X = 0; $X < $this->width; ++$X){
				for($Y = 0; $Y < $this->height; ++$Y){
					$this->generator->generateChunk($X, $Y, $Z);
					$this->generator->populateChunk($X, $Y, $Z);
				}
			}
			console("[NOTICE] Generating level ".ceil((($Z + 1)/$this->width) * 100)."%");
		}
		console("[NOTICE] Populating level");
		$this->generator->populateLevel();
		$this->level->setSpawn($this->generator->getSpawn());
		$this->level->save(true);
	}

}