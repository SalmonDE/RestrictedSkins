<?php
declare(strict_types = 1);

namespace SalmonDE\RestrictedSkins;

use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChangeSkinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\BinaryStream;

class Main extends PluginBase implements Listener {

    private $fallbackSkinData;

    public function onEnable(): void{
        $this->saveResource('config.yml');
        $this->saveResource('fallback.png');

        $fallbackSkin = new Skin('fallback', self::getSkinDataFromPNG($this->getDataFolder().'fallback.png'));

        if(!$fallbackSkin->isValid()){
            $this->getLogger()->error('Invalid skin supplied as fallback. Reverting to default one.');
            $fallbackSkin = new Skin('fallback', self::getSkinDataFromPNG($this->getFile().'/resources/fallback.png'));
        }

        $this->fallbackSkinData = $fallbackSkin->getSkinData();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    /**
    * @ignoreCancelled true
    */
    public function onPlayerLogin(PlayerLoginEvent $event): void{
        $event->getPlayer()->setSkin($this->getStrippedSkin($event->getPlayer()->getSkin()));
    }

    /**
    * @ignoreCancelled true
    */
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

        if(!$noCustomSkins && $this->getConfig()->get('disable-transparent-skins') === \true && $this->getSkinTransparencyPercentage($skinData) > $this->getConfig()->get('allowed-transparency')){
            $skinData = $this->fallbackSkinData;
        }

        $capeData = $this->getConfig()->get('disable-custom-capes') === \true ? '' : $skin->getCapeData();
        $geometryName = $this->getConfig()->get('disable-custom-geometry') === \true ? '' : $skin->getGeometryName();
        $geometryData = $this->getConfig()->get('disable-custom-geometry') === \true ? '' : $skin->getGeometryData();

        return new Skin($skin->getSkinId(), $skinData, $capeData, $geometryName, $geometryData);
    }

    public static function getSkinTransparencyPercentage(string $skinData): int{
        switch(\strlen($skinData)){
            case 8192:
                $maxX = 64;
                $maxY = 32;
                break;

            case 16384:
                $maxX = 64;
                $maxY = 64;
                break;

            case 65536:
                $maxX = 128;
                $maxY = 128;
                break;

            default:
                throw new InvalidArgumentException('Inappropriate skin data length: '.\strlen($skinData));
        }

        $stream = new BinaryStream($skinData);
        $transparentPixels = 0;

        for($y = 0; $y < $maxY; ++$y){
            for($x = 0; $x < $maxX; ++$x){
                $stream->getByte();
                $stream->getByte();
                $stream->getByte();

                $a = 127 - (int) \floor($stream->getByte() / 2);
                if($a > 0){
                    ++$transparentPixels;
                }
            }
        }

        return (int) \round($transparentPixels * 100 / ($maxX * $maxY));
    }

    public static function getSkinDataFromPNG(string $path) : string{
        $img = \imagecreatefrompng($path);
        [$k, $l] = \getimagesize($path);
        $bytes = "";

        for ($y = 0; $y < $l; ++$y) {
            for ($x = 0; $x < $k; ++$x) {
                $argb = \imagecolorat($img, $x, $y);
                $bytes .= \chr(($argb >> 16) & 0xff).\chr(($argb >> 8) & 0xff).\chr($argb & 0xff).\chr((~($argb >> 24) << 1) & 0xff);
            }
        }

        \imagedestroy($img);
        return $bytes;
    }
}
