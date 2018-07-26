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

    public function onEnable(): void{
        $this->saveResource('config.yml');
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
        $event->setNewSkin($this->getStrippedSkin($event->getNewSkin()));
    }

    public function getStrippedSkin(Skin $skin): Skin{
        $skinData = ($noCustomSkins = $this->getConfig()->get('disable-custom-skins') === \true) ? \str_repeat("\0xFF", 2048) : $skin->getSkinData();

        if(!$noCustomSkins && $this->getConfig()->get('disable-transparent-skins') === \true && $this->getSkinTransparencyPercentage($skinData) > $this->getConfig()->get('allowed-transparency')){
            $skinData = \str_repeat("\0xFF", 2048);
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
}
