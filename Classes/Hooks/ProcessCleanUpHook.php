<?php
namespace AOE\Crawler\Hooks;

/***************************************************************
 *  Copyright notice
 *
 *  (c) 2019 AOE GmbH <dev@aoe.com>
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

use AOE\Crawler\Controller\CrawlerController;
use AOE\Crawler\Domain\Repository\ProcessRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

/**
 * Class ProcessCleanUpHook
 * @package AOE\Crawler\Hooks
 */
class ProcessCleanUpHook
{
    /**
     * @var CrawlerController
     */
    private $crawlerController;

    /**
     * @var array
     */
    private $extensionSettings;

    /**
     * @var ProcessRepository
     */
    protected $processRepository;

    public function __construct()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->processRepository = $objectManager->get(ProcessRepository::class);
    }

    /**
     * Main function of process CleanUp Hook.
     *
     * @param CrawlerController $crawlerController Crawler Lib class
     *
     * @return void
     */
    public function crawler_init()
    {
        $this->processRepository->removeActiveOrphanProcesses();
        $this->processRepository->removeActiveProcessesOlderThanOneHour();
    }
}
