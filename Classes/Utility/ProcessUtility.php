<?php
namespace AOE\Crawler\Utility;

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

class ProcessUtility
{
    /**
     * Find dispatcher processes
     *
     * @return array
     * @codeCoverageIgnore
     */
    public static function findDispatcherProcesses()
    {
        $returnArray = [];
        if (!self::isOsWindows()) {
            // Not windows
            exec('ps aux | grep \'cli_dispatcher\'', $returnArray, $returnValue);
        } else {
            // Windows
            exec('tasklist | find \'cli_dispatcher\'', $returnArray, $returnValue);
        }
        return $returnArray;
    }

    /**
     * Check if the process still exists
     *
     * @param int $pid Process id to be checked.
     *
     * @return bool
     * @codeCoverageIgnore
     */
    public static function doProcessStillExists($pid)
    {
        $doProcessStillExists = false;
        if (!self::isOsWindows()) {
            // Not windows
            if (file_exists('/proc/' . $pid)) {
                $doProcessStillExists = true;
            }
        } else {
            // Windows
            exec('tasklist | find "' . $pid . '"', $returnArray, $returnValue);
            if (count($returnArray) > 0 && preg_match('/php/i', $returnValue[0])) {
                $doProcessStillExists = true;
            }
        }
        return $doProcessStillExists;
    }

    /**
     * Kills a process
     *
     * @param int $pid Process id to kill
     *
     * @return void
     * @codeCoverageIgnore
     */
    public static function killProcess($pid)
    {
        if (!self::isOsWindows()) {
            // Not windows
            posix_kill($pid, 9);
        } else {
            // Windows
            exec('taskkill /PID ' . $pid);
        }
    }

    /**
     * Check if OS is Windows
     *
     * @return bool
     * @codeCoverageIgnore
     */
    private static function isOsWindows()
    {
        if (TYPO3_OS === 'WIN') {
            return true;
        }
        return false;
    }


}
