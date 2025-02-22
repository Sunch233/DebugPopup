<?php

namespace sunch233\testPopup;

/*
 * testPopup
 *
 * Author : Sunch233
 * QQ2125696621
 *
 * SCAXE project
 */


use pocketmine\event\Listener;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\level\generator\biome\Biome;
use pocketmine\level\weather\Weather;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\CallbackTask;
use pocketmine\Server;
use pocketmine\utils\Utils;

class Main extends PluginBase implements Listener{

	public $lastTick;
	protected $tickInterval;
	/** @var Player[]  */
	protected $players = [];

	protected $msPerTick = 50; //default 50ms per tick

	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);

		$this->lastTick = microtime(true);
		$this->getServer()->getScheduler()->scheduleRepeatingTask(new CallbackTask([$this, 'onPopup']), 1);
		if($this->getServer()->getName() == 'SCAXE') $this->msPerTick = Server::TARGET_SECONDS_PER_TICK * 1000; //SCAXE 3.0+
		$this->tickInterval = array_fill(0, 10, $this->msPerTick);

		$this->getLogger()->info('enabled');
	}

	public function onJoin(PlayerJoinEvent $e){
		$p = $e->getPlayer();
		if($p->isOp()){
			$this->players[strtolower($p->getName())] = $p;
		}
	}

	public function onLeave(PlayerQuitEvent $e) {
		$p = $e->getPlayer();
		unset($this->players[strtolower($p->getName())]);
	}

	public function onPopup(){
		if(count($this->players) > 0){
			$server = $this->getServer();
			$players = $server->getOnlinePlayers();
			$levels = $server->getLevels();

			array_shift($this->tickInterval);
			$this->tickInterval[] = (microtime(true) - $this->lastTick) * 1000 - $this->msPerTick;
			$this->lastTick = microtime(true);

			$chunkCount = 0;
			$tileCount = 0;
			$tickingChunkCount = 0;
			$entities = 0;
			foreach($levels as $level){
				$chunkCount += count($level->getChunks());
				if($server->getName() == 'SCAXE'){
					$tickingChunkCount += $level->getTickingChunksCount(); //SCAXE
				}else{
					$tickingChunkCount = 'unsupport';
				}

				$entities += count($level->getEntities());
				$tileCount += count($level->getTiles());
			}
			$serverInfo = [
				'TPS' => $server->getTicksPerSecondAverage(),
				'MSPT' => round($server->getTickUsageAverage() / 100 * $this->msPerTick, 1) . "ms" ,
				'Deviation' => round(array_sum($this->tickInterval) / count($this->tickInterval), 1).'ms',
				'ChunksLoaded' => $tickingChunkCount . '/' . $chunkCount,
				'Tiles' => $tileCount,
				'Entities' => $entities,
				'Players' => count($players),

				'Memory' => round((Utils::getMemoryUsage() / 1024) / 1024, 2).'MB'
			];

			foreach ($this->players as $player){
				if(!$player->isOnline()){
					unset($this->players[strtolower($player->getName())]);
					continue;
				}

				$pos = $player->getPosition();
				$blockPos = $pos->subtract(0 ,1, 0)->floor();
				$block = $player->getLevel()->getBlock($blockPos);
				$level = $player->getLevel();

				if($server->getName() == 'SCAXE'){ //SCAXE
					$ping = $player->getPing().'ms';
				}else{
					$ping = 'unsupport';
				}

				switch($level->getWeather()->getWeather()){
					case Weather::SUNNY:
						$weather = 'sunny';
						break;
					case Weather::RAINY:
						$weather = 'rainy';
						break;
					case Weather::RAINY_THUNDER:
						$weather = 'rainy thunder';
						break;
					case Weather::THUNDER:
						$weather = 'thunder';
						break;
					default:
						$weather = 'unkonwn';
						break;
				}
				$playerInfo = [
					'Ping' => $ping,
					'Pos' => number_format($pos->x, 1, '.', '').' '.number_format($pos->y, 1, '.', '').' '.number_format($pos->z, 1, '.', ''),
					'Health' => $player->getHealth() . '/' . $player->getMaxHealth(),
					'Food' => $player->getFood(),
					'Exhaustion' => round($player->getExhaustion(), 2),
					'Saturation' => round($player->getSaturation(), 1),

					'Level' => $level->getName(),
					'TickMs' => round($level->getTickRateTime()),
					'Time' => $level->getTime(),
					'Biome' => Biome::getBiome($level->getBiomeId($pos->x, $pos->z))->getName(),
					'Exp' => $player->getXpLevel().'L/'.$player->getExp(),
					'OnGround' => $player->isOnGround() ? 'true' : 'false',
					'Moving' => $player->isMoving() ? ($server->getName() == 'SCAXE' and $player->isSwimming()) ? 'swimming' : (($server->getName() == 'SCAXE' and $player->isClimbing()) ? 'climbing' : 'moving') : 'false', //mess

					'Block' => $block->getName(),
					'aabbCount' => $server->getName() == 'SCAXE' ? count($block->getCollisionBoxes()) : ($block->getBoundingBox() == null ? 0 : 1),
					'Light' => $level->getBlockLightAt($pos->x, $pos->y, $pos->z),
					'SkyLight' => $server->getName() == 'SCAXE' ? $level->getRealBlockSkyLightAt($pos->x, $pos->y, $pos->z) : $level->getBlockLightAt($pos->x, $pos->y, $pos->z),
					'Weather' => $weather,
					'§a'.date('H') => '§a'.date('i: s')
					//1 line empty
				];

				$this->sendPopup($player, array_merge($serverInfo, $playerInfo));
			}
		}
	}

	public function sendPopup(Player $player, $datas = []){
		$maxlen = 0;
		$index = 0;
		$lines = [
			1 => '',
			2 => '',
			3 => '',
			4 => '',
			5 => '',
			6 => '',
			7 => '',
		];
		$offset = 0;
		foreach ($datas as $key => $description){
			++$index;
			if($index > 7){
				$index = 1;
				$offset += $maxlen + 1;
				$maxlen = 0;
			}

			$line = "§f$key: §b$description";
			$len = strlen($line);
			if($len > $maxlen) $maxlen = $len;
			$baseLen = strlen($lines[$index]);
			if($baseLen < $offset){
				$line = str_repeat(" ", $offset - $baseLen) . $line;
			}
			$lines[$index] .= $line;
		}
		$txt = '';

		foreach ($lines as $line){
			$txt .= $line . "\n";
		}
		if($player->getProtocol() <= 46){ //0.14.1-
			$player->sendTip($txt.'‘');
		}else{
			$player->sendPopup($txt.'‘');
		}

	}
}
