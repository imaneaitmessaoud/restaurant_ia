<?php

namespace App\EventListener;

use App\Entity\MenuItem;
use App\Service\MenuExportService;
use Doctrine\ORM\Event\LifecycleEventArgs;

class MenuItemListener
{
    private $exportService;

    public function __construct(MenuExportService $exportService)
    {
        $this->exportService = $exportService;
    }

    public function postPersist(MenuItem $menuItem, LifecycleEventArgs $args): void
    {
        $this->exportService->exportToExcel();
    }

    public function postUpdate(MenuItem $menuItem, LifecycleEventArgs $args): void
    {
        $this->exportService->exportToExcel();
    }

    public function postRemove(MenuItem $menuItem, LifecycleEventArgs $args): void
    {
        $this->exportService->exportToExcel();
    }
}
