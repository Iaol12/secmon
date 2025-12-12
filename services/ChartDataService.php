<?php

namespace app\services;

use app\models\SecurityEvents;
use app\models\Filter;
use Yii;

class ChartDataService
{
    public function getFilteredEventsPieChart($filterId, $field)
    {
        $query = SecurityEvents::find();
        $label = "CAST(" . $field . " AS text) as label";
        $value = "count(" . $field . ") as count";
        
        $query->select([$label, $value])
            ->groupby(["label"])
            ->orderBy(['label' => SORT_ASC]);

        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (!empty($filter)) {
                $query->applyFilter($filter);
            }
        }

        $filteredData = $query->asArray()->all();
        Yii::$app->cache->flush();

        return $filteredData;
    }

    public function getFilteredEventsBarChart($filterId, $field)
    {
        $query = SecurityEvents::find();
        $label = "CAST(" . $field . " AS text) as label";
        $value = "count(" . $field . ") as count";
        
        $query->select([$label, $value])
            ->groupby(["label"])
            ->orderBy(['label' => SORT_ASC]);

        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (!empty($filter)) {
                $query->applyFilter($filter);
            }
        }

        $filteredData = $query->asArray()->all();
        Yii::$app->cache->flush();

        return $filteredData;
    }

    public function getFilteredEventsLineChart($filterId, $timeframe = null, $granularity = '2H')
    {
        $range = !empty($timeframe) ? $this->parseTimeframeToDateInterval($timeframe) : 'P1W';
        $interval = new \DateInterval($this->parseGranularityToDateInterval($granularity));
        $sqlGroupingFormat = $this->getSqlGroupingFormat($granularity);
        
        $dt = new \DateTime('now', new \DateTimeZone('Europe/Bratislava'));
        $dt->sub(new \DateInterval($range));
        $startDate = $dt->format("Y-m-d H:i:s");

        $query = SecurityEvents::find()
            ->select(["to_char(datetime, '$sqlGroupingFormat') as x", "count(id) as y"])
            ->groupBy(["x"])
            ->orderBy(['x' => SORT_ASC])
            ->andWhere(['>', "datetime", $startDate]);

        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (!empty($filter)) {
                $query->applyFilter($filter);
            }
        }

        $filteredData = $query->asArray()->all();
        $chartData = [];
        $now = new \DateTime('now', new \DateTimeZone('Europe/Bratislava'));
        $dt = $this->adjustStartDateForGranularity($dt, $granularity);
        $phpDateFormat = $this->getPhpDateFormat($granularity);
        $i = 0;
        
        while ($dt <= $now) {
            $dt_end = clone $dt;
            $dt_end->add($interval);
            $dt_end_str = $dt_end->format($phpDateFormat);
            $current_data_point_y = 0;

            while (isset($filteredData[$i]) && strcmp($filteredData[$i]['x'], $dt_end_str) < 0) {
                $current_data_point_y += intval($filteredData[$i]['y']);
                $i++;
            }

            $chartData[] = ['x' => $dt->format('d.m.Y G:i'), 'y' => $current_data_point_y];
            $dt->add($interval);
        }

        return $chartData;
    }

    private function parseTimeframeToDateInterval(string $timeframe): string
    {
        $timeUnit = substr($timeframe, -1);
        if (in_array($timeUnit, ['Y', 'M', 'W', 'D'])) {
            return 'P' . $timeframe;
        }
        return $timeUnit == 'm' ? 'PT' . substr($timeframe, 0, -1) . 'M' : 'PT' . $timeframe;
    }

    private function parseGranularityToDateInterval(string $granularity): string
    {
        preg_match('/^(\d+)\s*(hour|day|week|month|year|h|m|d|w|y)$/i', rtrim($granularity, 's'), $matches);
        if (empty($matches)) return 'P1W';

        $amount = $matches[1];
        $unit = strtolower($matches[2]);
        $map = [
            'year' => 'P' . $amount . 'Y', 'y' => 'P' . $amount . 'Y',
            'month' => 'P' . $amount . 'M', 'm' => 'P' . $amount . 'M',
            'week' => 'P' . $amount . 'W', 'w' => 'P' . $amount . 'W',
            'day' => 'P' . $amount . 'D', 'd' => 'P' . $amount . 'D',
            'hour' => 'PT' . $amount . 'H', 'h' => 'PT' . $amount . 'H',
        ];
        return $map[$unit] ?? 'P1W';
    }

    public function isValidISO8601(string $granularity): bool
    {
        preg_match('/^(\d+)\s*(hour|day|week|month|year|h|m|d|w|y)s?$/i', rtrim($granularity, 's'), $matches);
        return !empty($matches);
    }

    private function getSqlGroupingFormat(string $granularity): string
    {
        $g = rtrim(strtolower($granularity), 's');
        if (strpos($g, 'hour') !== false || strpos($g, 'h') !== false) return 'YYYY-MM-DD HH24';
        if (strpos($g, 'day') !== false || strpos($g, 'd') !== false) return 'YYYY-MM-DD';
        if (strpos($g, 'week') !== false || strpos($g, 'w') !== false) return 'YYYY-WW';
        if (strpos($g, 'month') !== false || strpos($g, 'mon') !== false) return 'YYYY-MM';
        if (strpos($g, 'year') !== false || strpos($g, 'y') !== false) return 'YYYY';
        return 'YYYY-MM-DD';
    }

    private function getPhpDateFormat(string $granularity): string
    {
        $g = rtrim(strtolower($granularity), 's');
        if (strpos($g, 'hour') !== false || strpos($g, 'h') !== false) return 'Y-m-d H';
        if (strpos($g, 'day') !== false || strpos($g, 'd') !== false) return 'Y-m-d';
        if (strpos($g, 'week') !== false || strpos($g, 'w') !== false) return 'Y-W';
        if (strpos($g, 'month') !== false || strpos($g, 'mon') !== false) return 'Y-m';
        if (strpos($g, 'year') !== false || strpos($g, 'y') !== false) return 'Y';
        return 'Y-m-d';
    }

    private function adjustStartDateForGranularity(\DateTime $dt, string $granularity): \DateTime
    {
        $g = rtrim(strtolower($granularity), 's');
        $adjustedDt = clone $dt;

        if (strpos($g, 'week') !== false || strpos($g, 'w') !== false) {
            $adjustedDt->setISODate($adjustedDt->format('Y'), $adjustedDt->format('W'), 1);
        } elseif (strpos($g, 'month') !== false || strpos($g, 'mon') !== false) {
            $adjustedDt->setDate($adjustedDt->format('Y'), $adjustedDt->format('m'), 1);
            $adjustedDt->setTime(0, 0, 0);
        } elseif (strpos($g, 'year') !== false || strpos($g, 'y') !== false) {
            $adjustedDt->setDate($adjustedDt->format('Y'), 1, 1);
            $adjustedDt->setTime(0, 0, 0);
        } elseif (strpos($g, 'day') !== false || strpos($g, 'd') !== false) {
            $adjustedDt->setTime(0, 0, 0);
        } elseif (strpos($g, 'hour') !== false || strpos($g, 'h') !== false) {
            $adjustedDt->setTime($adjustedDt->format('H'), 0, 0);
        }

        return $adjustedDt < $dt ? $dt : $adjustedDt;
    }

    /**
     * Get filtered events for table widget with specified columns
     * @param integer $filterId
     * @param integer $page
     * @param array $columns
     * @param string $timeframe
     * @return array
     */
    public function getFilteredEventsTableWidget($filterId, $page, $columns = [], $timeframe = '')
    {
        $query = SecurityEvents::find();
        $page = max(1, intval($page)) - 1;

        // Apply filter if provided
        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (!empty($filter)) {
                $query->applyFilter($filter);
            }
        }

        // Apply timeframe filter if provided
        if (!empty($timeframe)) {
            $range = $this->parseTimeframeToDateInterval($timeframe);
            $dt = new \DateTime('now', new \DateTimeZone('Europe/Bratislava'));
            $dt->sub(new \DateInterval($range));
            $startDate = $dt->format("Y-m-d H:i:s");
            $query->andWhere(['>=', 'datetime', $startDate]);
        }

        // Select only specified columns, or all if none specified
        if (!empty($columns) && is_array($columns)) {
            $query->select($columns);
        }

        $filteredData = $query
            ->orderBy(['datetime' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(10)
            ->offset(10 * $page)
            ->asArray()
            ->all();

        Yii::$app->cache->flush();

        return $filteredData;
    }

    /**
     * Get count of filtered events for table widget
     * @param integer $filterId
     * @param string $timeframe
     * @return integer
     */
    public function getFilteredEventsCountForTableWidget($filterId, $timeframe = '')
    {
        $query = SecurityEvents::find();
        $query->select(["count(*) as count"]);

        // Apply filter if provided
        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (!empty($filter)) {
                $query->applyFilter($filter);
            }
        }

        // Apply timeframe filter if provided
        if (!empty($timeframe)) {
            $range = $this->parseTimeframeToDateInterval($timeframe);
            $dt = new \DateTime('now', new \DateTimeZone('Europe/Bratislava'));
            $dt->sub(new \DateInterval($range));
            $startDate = $dt->format("Y-m-d H:i:s");
            $query->andWhere(['>=', 'datetime', $startDate]);
        }

        $result = $query->asArray()->one();
        Yii::$app->cache->flush();

        return isset($result['count']) ? intval($result['count']) : 0;
    }
}