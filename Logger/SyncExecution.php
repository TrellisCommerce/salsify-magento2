<?php

namespace Trellis\Salsify\Logger;

/**
 * Class SyncExecution
 * @package Trellis\Salsify\Logger
 */
class SyncExecution
{
    /**
     * @var
     */
    private $_startTime;
    /**
     * @var
     */
    private $_endTime;

    /**
     * SyncExecution constructor.
     */
    public function __construct()
    {
        // set timezone
        date_default_timezone_set('UTC');
    }

    // set the start time

    /**
     *
     */
    public function start()
    {
        $this->_startTime = microtime(true);
    }

    // set the end time

    /**
     *
     */
    public function end()
    {
        $this->_endTime = microtime(true);
    }

    // format start date and time

    /**
     * @return false|string
     */
    public function getStartTime()
    {
        return $this->getDateTime($this->_startTime);
    }

    // format end date and time

    /**
     * @return bool|false|string
     */
    public function getEndTime()
    {
        if (!$this->_endTime) {
            return false;
        }
        return $this->getDateTime($this->_endTime);
    }

    // return time elapsed from start

    /**
     * @return mixed
     */
    public function getElapsedTime()
    {
        return $this->getExecutionTime(microtime(true));
    }

    // return total execution time

    /**
     * @return bool
     */
    public function getTotalExecutionTime()
    {
        if (!$this->_endTime) {
            return false;
        }
        return $this->getExecutionTime($this->_endTime);
    }

    // return start time, stop time, and total execution time

    /**
     * @return array|bool
     */
    public function getFullStats()
    {
        if (!$this->_endTime) {
            return false;
        }

        $stats = [];
        $stats['start_time']        = $this->getDateTime($this->_startTime);
        $stats['end_time']          = $this->getDateTime($this->_endTime);
        $stats['execution_time']    = $this->getExecutionTime($this->_endTime);

        return $stats;
    }

    // format date and time

    /**
     * @param $time
     * @return false|string
     */
    private function getDateTime($time)
    {
        return date("Y-m-d H:i:s", $time);
    }

    // get execution time

    /**
     * @param $time
     * @return mixed
     */
    private function getExecutionTime($time)
    {
        return $time - $this->_startTime;
    }
}
