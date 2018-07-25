<?php
declare(strict_types = 1);

namespace SalmonDE\RestrictedSkins;

use pocketmine\entity\Skin;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChangeSkinEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\plugin\PluginBase;

class Main extends PluginBase implements Listener {

    public function onEnable(): void{
        $this->saveResource('config.yml');
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
    }

    public function onPlayerLogin(PlayerLoginEvent $event): void{
        $event->getPlayer()->setSkin($this->getStrippedSkin($event->getPlayer()->getSkin()));
    }

    public function onPlayerChangeSkin(PlayerChangeSkinEvent $event): void{
        $event->setNewSkin($this->getStrippedSkin($event->getNewSkin()));
    }

    public function getStrippedSkin(Skin $skin): Skin{
        $skinData = $this->getConfig()->get('disable-custom-skins') === true ? \str_repeat("\0xFF", 2048) : $skin->getSkinData();
        $capeData = $this->getConfig()->get('disable-custom-capes') === true ? '' : $skin->getCapeData();
        $geometryName = $this->getConfig()->get('disable-custom-geometry') === true ? '' : $skin->getGeometryName();
        $geometryData = $this->getConfig()->get('disable-custom-geometry') === true ? '' : $skin->getGeometryData();

        return new Skin($skin->getSkinId(), $skinData, $capeData, $geometryName, $geometryData);
    }
}
