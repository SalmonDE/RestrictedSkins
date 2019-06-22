<?php
declare(strict_types = 1);

namespace SalmonDE\RestrictedSkins;

use InvalidArgumentException;
use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChangeSkinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {

	public const BOUNDS_64_64 = 0;
	public const BOUNDS_64_32 = self::BOUNDS_64_64;
	public const BOUNDS_128_128 = 1;

	private $fallbackSkinData;
	private $skinBounds = [];

	protected function onEnable(): void{
		$this->saveResource('config.yml');
		$this->saveResource('fallback.png');

		try{
			$fallbackSkin = new Skin('fallback', self::getSkinDataFromPNG($this->getDataFolder().'fallback.png'));
		}catch(InvalidArgumentException $e){
			$this->getLogger()->error('Invalid skin supplied as fallback. Reverting to default one.');
			$fallbackSkin = new Skin('fallback', self::getSkinDataFromPNG($this->getFile().'/resources/fallback.png'));
		}

		$this->fallbackSkinData = $fallbackSkin->getSkinData();

		$cubes = $this->getCubes(json_decode(stream_get_contents($this->getResource('humanoid.json')), true)['geometry.humanoid']);
		$this->skinBounds[self::BOUNDS_64_64] = $this->getSkinBounds($cubes);
		$this->skinBounds[self::BOUNDS_128_128] = $this->getSkinBounds($cubes, 2.0);

		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}

	public function onPlayerLogin(PlayerLoginEvent $event): void{
		$event->getPlayer()->setSkin($this->getStrippedSkin($event->getPlayer()->getSkin()));
	}

	public function onPlayerChangeSkin(PlayerChangeSkinEvent $event): void{
		if($this->getConfig()->get('disable-ingame-skin-change') === \true){
			$event->setCancelled();
		}

		$event->setNewSkin($this->getStrippedSkin($event->getNewSkin()));
	}

	public function getFallbackSkinData(): string{
		return $this->fallbackSkinData;
	}

	public function getStrippedSkin(Skin $skin): Skin{
		$skinData = ($noCustomSkins = $this->getConfig()->get('disable-custom-skins') === \true) ? $this->fallbackSkinData : $skin->getSkinData();

		if(!$noCustomSkins && $this->getConfig()->get('disable-transparent-skins') === \true && $this->getSkinTransparencyPercentage($skinData) > $this->getConfig()->get('allowed-transparency-percentage')){
			$skinData = $this->fallbackSkinData;
		}

		$capeData = $this->getConfig()->get('disable-custom-capes') === \true ? '' : $skin->getCapeData();
		$geometryName = $this->getConfig()->get('disable-custom-geometry') === \true && $skin->getGeometryName() !== 'geometry.humanoid.customSlim' ? 'geometry.humanoid.custom' : $skin->getGeometryName();
		$geometryData = $this->getConfig()->get('disable-custom-geometry') === \true ? '' : $skin->getGeometryData();

		return new Skin($skin->getSkinId(), $skinData, $capeData, $geometryName, $geometryData);
	}

	public function getSkinTransparencyPercentage(string $skinData): int{
		switch(\strlen($skinData)){
			case 8192:
				$maxX = 64;
				$maxY = 32;

				$bounds = $this->skinBounds[self::BOUNDS_64_32];
				break;

			case 16384:
				$maxX = 64;
				$maxY = 64;

				$bounds = $this->skinBounds[self::BOUNDS_64_64];
				break;

			case 65536:
				$maxX = 128;
				$maxY = 128;

				$bounds = $this->skinBounds[self::BOUNDS_128_128];
				break;

			default:
				throw new InvalidArgumentException('Inappropriate skin data length: '.\strlen($skinData));
		}

		$transparentPixels = $pixels = 0;

		foreach($bounds as $bound){
			if($bound['max']['x'] > $maxX || $bound['max']['y'] > $maxY){
				continue;
			}

			for($y = $bound['min']['y']; $y <= $bound['max']['y']; $y++){
				for($x = $bound['min']['x']; $x <= $bound['max']['x']; $x++){
					$key = (($maxX * $y) + $x) * 4;
					$a = \ord($skinData[$key + 3]);

					if($a < 127){
						++$transparentPixels;
					}

					++$pixels;
				}
			}
		}

		return (int) \round($transparentPixels * 100 / max(1, $pixels));
	}

	public static function getSkinDataFromPNG(string $path): string{
		$img = \imagecreatefrompng($path);
		[$k, $l] = \getimagesize($path);
		$bytes = '';

		for ($y = 0; $y < $l; ++$y) {
			for ($x = 0; $x < $k; ++$x) {
				$argb = \imagecolorat($img, $x, $y);
				$bytes .= \chr(($argb >> 16) & 0xff).\chr(($argb >> 8) & 0xff).\chr($argb & 0xff).\chr((~($argb >> 24) << 1) & 0xff);
			}
		}

		\imagedestroy($img);
		return $bytes;
	}

	public function getCubes(array $geometryData): array{
		$cubes = [];
		foreach($geometryData['bones'] as $bone){
			if(!isset($bone['cubes'])){
				continue;
			}

			if($bone['mirror'] ?? false){
				throw new InvalidArgumentException('Unsupported geometry data');
			}

			foreach($bone['cubes'] as $cubeData){
				$cube = [];
				$cube['x'] = $cubeData['size'][0];
				$cube['y'] = $cubeData['size'][1];
				$cube['z'] = $cubeData['size'][2];
				$cube['uvX'] = $cubeData['uv'][0];
				$cube['uvY'] = $cubeData['uv'][1];

				$cubes[] = $cube;
			}
		}

		return $cubes;
	}

	public function getSkinBounds(array $cubes, float $scale = 1.0): array{
		$bounds = [];
		foreach($cubes as $cube){
			$x = (int) ($scale * $cube['x']);
			$y = (int) ($scale * $cube['y']);
			$z = (int) ($scale * $cube['z']);
			$uvX = (int) ($scale * $cube['uvX']);
			$uvY = (int) ($scale * $cube['uvY']);

			$bounds[] = ['min' => ['x' => $uvX + $z, 'y' => $uvY], 'max' => ['x' => $uvX + $z + (2 * $x) - 1, 'y' => $uvY + $z - 1]];
			$bounds[] = ['min' => ['x' => $uvX, 'y' => $uvY + $z], 'max' => ['x' => $uvX + (2 * ($z + $x)) - 1, 'y' => $uvY + $z + $y - 1]];
		}

		return $bounds;
	}
}
