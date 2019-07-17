<?php
namespace AOE\Crawler\Domain\Repository;

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

use AOE\Crawler\Domain\Model\Process;
use AOE\Crawler\Domain\Model\ProcessCollection;
use AOE\Crawler\Utility\ExtensionSettingUtility;
use AOE\Crawler\Utility\ProcessUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Class ProcessRepository
 *
 * @package AOE\Crawler\Domain\Repository
 */
class ProcessRepository extends Repository
{

    const TABLE_CRAWLER_PROCESS = 'tx_crawler_process';

    /**
     * @var array
     */
    protected $extensionSettings = [];

    /**
     * @var QueueRepository
     */
    protected $queueRepository;

    public function initializeObject()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $this->extensionSettings = ExtensionSettingUtility::loadExtensionSettings();
        $this->queueRepository = $objectManager->get(QueueRepository::class);
    }

    /**
     * This method is used to find all cli processes within a limit.
     *
     * @return ProcessCollection
     */
    public function findAll()
    {
        /** @var ProcessCollection $collection */
        $collection = GeneralUtility::makeInstance(ProcessCollection::class);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_CRAWLER_PROCESS);

        $statement = $queryBuilder
            ->select('*')
            ->from(self::TABLE_CRAWLER_PROCESS)
            ->orderBy('ttl', 'DESC')
            ->execute();

        while ($row = $statement->fetch()) {
            $process = new Process();
            $process->setProcessId($row['process_id']);
            $process->setTtl($row['ttl']);
            $process->setActive($row['active'] ? true : false);
            $process->setAssignedItemsCount($row['assigned_items_count']);
            $process->setDeleted($row['deleted'] ? true : false);
            $collection->append($process);
        }

        return $collection;
    }

    public function findAllActive()
    {
        /** @var ProcessCollection $collection */
        $collection = GeneralUtility::makeInstance(ProcessCollection::class);
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_CRAWLER_PROCESS);

        $statement = $queryBuilder
            ->select('*')
            ->from(self::TABLE_CRAWLER_PROCESS)
            ->where(
                $queryBuilder->expr()->eq('active', 1),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->orderBy('ttl', 'DESC')
            ->execute();

        while ($row = $statement->fetch()) {
            $process = new Process();
            $process->setProcessId($row['process_id']);
            $process->setTtl($row['ttl']);
            $process->setActive($row['active'] ? true : false);
            $process->setAssignedItemsCount($row['assigned_items_count']);
            $process->setDeleted($row['deleted'] ? true : false);
            $collection->append($process);
        }

        return $collection;
    }

    /**
     * @param int $processId
     *
     * @return void
     */
    public function removeByProcessId($processId)
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE_CRAWLER_PROCESS);
        $connection->delete(self::TABLE_CRAWLER_PROCESS, ['process_id' => (int)$processId]);

    }

    /**
     * Returns the number of active processes.
     *
     * @return integer
     */
    public function countActive()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_CRAWLER_PROCESS);
        $count = $queryBuilder
            ->count('*')
            ->from(self::TABLE_CRAWLER_PROCESS)
            ->where(
                $queryBuilder->expr()->eq('active', 1),
                $queryBuilder->expr()->eq('deleted', 0)
            )
            ->execute()
            ->fetchColumn(0);

        return $count;
    }

    /**
     * @return array|null
     *
     * Function is moved from ProcessCleanUpHook
     * TODO: Check why we need both getActiveProcessesOlderThanOneHour and getActiveOrphanProcesses, the get getActiveOrphanProcesses does not really check for Orphan in this implementation.
     */
    public function getActiveProcessesOlderThanOneHour()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_CRAWLER_PROCESS);
        $activeProcesses = [];
        $statement = $queryBuilder
            ->select('process_id', 'system_process_id')
            ->from(self::TABLE_CRAWLER_PROCESS)
            ->where(
                $queryBuilder->expr()->lte('ttl', intval(time() - $this->extensionSettings['processMaxRunTime'] - 3600)),
                $queryBuilder->expr()->eq('active', 1)
            )
            ->execute();

        while ($row = $statement->fetch()) {
            $activeProcesses[] = $row;
        }

        return $activeProcesses;
    }

    /**
     * Function is moved from ProcessCleanUpHook
     *
     * @return array
     *
     * @see getActiveProcessesOlderThanOneHour
     */
    public function getActiveOrphanProcesses()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_CRAWLER_PROCESS);
        $statement = $queryBuilder
            ->select('process_id', 'system_process_id')
            ->from(self::TABLE_CRAWLER_PROCESS)
            ->where(
                $queryBuilder->expr()->lte('ttl', intval(time() - $this->extensionSettings['processMaxRunTime'])),
                $queryBuilder->expr()->eq('active', 1)
            )
            ->execute()->fetchAll();

        return $statement;
    }

    /**
     * Remove active processes older than one hour
     *
     * @return void
     */
    public function removeActiveProcessesOlderThanOneHour()
    {
        $results = $this->getActiveProcessesOlderThanOneHour();
        if (!is_array($results)) {
            return;
        }
        foreach ($results as $result) {
            $systemProcessId = (int)$result['system_process_id'];
            $processId = $result['process_id'];
            if ($systemProcessId > 1) {
                if (ProcessUtility::doProcessStillExists($systemProcessId)) {
                    ProcessUtility::killProcess($systemProcessId);
                }
                $this->removeProcessFromProcesslist($processId);
            }
        }
    }

    /**
     * Removes active orphan processes from process list
     *
     * @return void
     */
    public function removeActiveOrphanProcesses()
    {
        $results = $this->getActiveOrphanProcesses();
        if (!is_array($results)) {
            return;
        }
        foreach ($results as $result) {
            $processExists = false;
            $systemProcessId = (int)$result['system_process_id'];
            $processId = $result['process_id'];
            if ($systemProcessId > 1) {
                $dispatcherProcesses = ProcessUtility::findDispatcherProcesses();
                if (!is_array($dispatcherProcesses) || empty($dispatcherProcesses)) {
                    $this->removeProcessFromProcesslist($processId);
                    return;
                }
                foreach ($dispatcherProcesses as $process) {
                    $responseArray = $this->createResponseArray($process);
                    if ($systemProcessId === (int)$responseArray[1]) {
                        $processExists = true;
                    };
                }
                if (!$processExists) {
                    $this->removeProcessFromProcesslist($processId);
                }
            }
        }
    }

    /**
     * Create response array
     * Convert string to array with space character as delimiter,
     * removes all empty records to have a cleaner array
     *
     * @param string $string String to create array from
     *
     * @return array
     *
     */
    private function createResponseArray($string)
    {
        $responseArray = GeneralUtility::trimExplode(' ', $string, true);
        $responseArray = array_values($responseArray);
        return $responseArray;
    }

    /**
     * Remove a process from processlist
     *
     * @param string $processId Unique process Id.
     *
     * @return void
     */
    private function removeProcessFromProcesslist($processId)
    {
        $this->removeByProcessId($processId);
        $this->queueRepository->unsetQueueProcessId($processId);
    }

    /**
     * Returns the number of processes that live longer than the given timestamp.
     *
     * @param integer $ttl
     *
     * @return integer
     */
    public function countNotTimeouted($ttl)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_CRAWLER_PROCESS);
        $count = $queryBuilder
            ->count('*')
            ->from(self::TABLE_CRAWLER_PROCESS)
            ->where(
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->gt('ttl', intval($ttl))
            )
            ->execute()
            ->fetchColumn(0);

        return $count;
    }

    /**
     * Counts all in repository
     *
     * @return integer
     */
    public function countAll()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_CRAWLER_PROCESS);
        $count = $queryBuilder
            ->count('*')
            ->from(self::TABLE_CRAWLER_PROCESS)
            ->execute()
            ->fetchColumn(0);
        return $count;
    }

    /**
     * @param $processId
     *
     * @return bool|string
     */
    public function countAllByProcessId($processId)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE_CRAWLER_PROCESS);
        $count = $queryBuilder
            ->count('*')
            ->from(self::TABLE_CRAWLER_PROCESS)
            ->where(
                $queryBuilder->expr()->eq('process_id', $queryBuilder->createNamedParameter($processId))
            )
            ->execute()
            ->fetchColumn(0);
        return $count;
    }

    /**
     * Get limit clause
     *
     * @param integer $itemCount
     * @param integer $offset
     *
     * @return string
     */
    public static function getLimitFromItemCountAndOffset($itemCount, $offset)
    {
        $itemCount = filter_var($itemCount, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'default' => 20]]);
        $offset = filter_var($offset, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'default' => 0]]);
        $limit = $offset . ', ' . $itemCount;

        return $limit;
    }

    /**
     * @return void
     */
    public function deleteProcessesWithoutItemsAssigned()
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE_CRAWLER_PROCESS);
        $connection->delete(self::TABLE_CRAWLER_PROCESS, ['assigned_items_count' => 0]);
    }

    public function deleteProcessesMarkedAsDeleted()
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable(self::TABLE_CRAWLER_PROCESS);
        $connection->delete(self::TABLE_CRAWLER_PROCESS, ['deleted' => 1]);
    }

    /**
     * Returns an instance of the TYPO3 database class.
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     * @deprecated since crawler v7.0.0, will be removed in crawler v8.0.0.
     */
    protected function getDB()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
