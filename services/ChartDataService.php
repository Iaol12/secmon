<?php

namespace app\services;

use app\models\SecurityEvents;
use app\models\Filter;
use Yii;

class ChartDataService
{
    public function getFilteredEventsPieChart($filterId, $field, $timeframe = null, $sinceTimestamp = null)
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

        // Apply timeframe filter if provided
        if (!empty($timeframe)) {
            $range = $this->parseTimeframeToDateInterval($timeframe);
            $dt = new \DateTime('now', new \DateTimeZone('Europe/Bratislava'));
            $dt->sub(new \DateInterval($range));
            $startDate = $dt->format("Y-m-d H:i:s");
            $query->andWhere(['>=', 'datetime', $startDate]);
        }

        // Apply timestamp filter if provided (for incremental updates)
        if (!empty($sinceTimestamp)) {
            // Subtract small buffer to avoid missing edge-case events due to precision
            try {
                $dt = new \DateTime($sinceTimestamp, new \DateTimeZone('UTC'));
                $dt->sub(new \DateInterval('PT1S'));
                $safeTimestamp = $dt->format("Y-m-d H:i:s");
                $query->andWhere(['>', 'datetime', $safeTimestamp]);
            } catch (\Exception $e) {
                // If parsing fails, just use the timestamp as-is
                $query->andWhere(['>', 'datetime', $sinceTimestamp]);
            }
        }

        $filteredData = $query->asArray()->all();
        Yii::$app->cache->flush();

        return $filteredData;
    }

    public function getFilteredEventsBarChart($filterId, $field, $timeframe = null, $sinceTimestamp = null)
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

        // Apply timeframe filter if provided
        if (!empty($timeframe)) {
            $range = $this->parseTimeframeToDateInterval($timeframe);
            $dt = new \DateTime('now', new \DateTimeZone('Europe/Bratislava'));
            $dt->sub(new \DateInterval($range));
            $startDate = $dt->format("Y-m-d H:i:s");
            $query->andWhere(['>=', 'datetime', $startDate]);
        }

        // Apply timestamp filter if provided (for incremental updates)
        if (!empty($sinceTimestamp)) {
            // Subtract small buffer to avoid missing edge-case events due to precision
            try {
                $dt = new \DateTime($sinceTimestamp, new \DateTimeZone('UTC'));
                $dt->sub(new \DateInterval('PT1S'));
                $safeTimestamp = $dt->format("Y-m-d H:i:s");
                $query->andWhere(['>', 'datetime', $safeTimestamp]);
            } catch (\Exception $e) {
                // If parsing fails, just use the timestamp as-is
                $query->andWhere(['>', 'datetime', $sinceTimestamp]);
            }
        }

        $filteredData = $query->asArray()->all();
        Yii::$app->cache->flush();

        return $filteredData;
    }

    public function getFilteredEventsLineChart($filterId, $timeframe = null, $granularity = '2H', $sinceTimestamp = null)
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

        // Apply timestamp filter if provided (for incremental updates)
        if (!empty($sinceTimestamp)) {
            // Subtract small buffer to avoid missing edge-case events due to precision
            try {
                $dtSince = new \DateTime($sinceTimestamp, new \DateTimeZone('UTC'));
                $dtSince->sub(new \DateInterval('PT1S'));
                $safeTimestamp = $dtSince->format("Y-m-d H:i:s");
                $query->andWhere(['>', 'datetime', $safeTimestamp]);
            } catch (\Exception $e) {
                // If parsing fails, just use the timestamp as-is
                $query->andWhere(['>', 'datetime', $sinceTimestamp]);
            }
        }

        $filteredData = $query->asArray()->all();
        $chartData = [];
        $now = new \DateTime('now', new \DateTimeZone('Europe/Bratislava'));
        // Anchor start date to fixed interval boundaries (not relative to current time)
        $dt = $this->anchorToIntervalBoundary($dt, $granularity);
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
     * Anchor a DateTime to the nearest fixed interval boundary in the past
     * This ensures intervals remain consistent over time for real-time updates
     */
    private function anchorToIntervalBoundary(\DateTime $dt, string $granularity): \DateTime
    {
        $g = rtrim(strtolower($granularity), 's');
        $anchored = clone $dt;

        if (strpos($g, 'year') !== false || strpos($g, 'y') !== false) {
            // Anchor to January 1st of that year
            $anchored->setDate($anchored->format('Y'), 1, 1);
            $anchored->setTime(0, 0, 0);
        } elseif (strpos($g, 'month') !== false || strpos($g, 'mon') !== false) {
            // Anchor to first day of that month
            $anchored->setDate($anchored->format('Y'), $anchored->format('m'), 1);
            $anchored->setTime(0, 0, 0);
        } elseif (strpos($g, 'week') !== false || strpos($g, 'w') !== false) {
            // Anchor to Monday of that week
            $anchored->setISODate($anchored->format('Y'), $anchored->format('W'), 1);
            $anchored->setTime(0, 0, 0);
        } elseif (strpos($g, 'day') !== false || strpos($g, 'd') !== false) {
            // Anchor to midnight of that day
            $anchored->setTime(0, 0, 0);
        } elseif (strpos($g, 'hour') !== false || strpos($g, 'h') !== false) {
            // Anchor to the top of the hour
            preg_match('/^(\d+)\s*h/i', $g, $matches);
            if (!empty($matches[1])) {
                $hours = intval($matches[1]);
                $currentHour = intval($anchored->format('H'));
                // Round down to nearest interval boundary
                $boundaryHour = floor($currentHour / $hours) * $hours;
                $anchored->setTime($boundaryHour, 0, 0);
            } else {
                $anchored->setTime($anchored->format('H'), 0, 0);
            }
        }

        return $anchored;
    }


    /**
     * Get filtered events for table widget with specified columns
     * @param integer $filterId
     * @param integer $page
     * @param array $columns
     * @param string $timeframe
     * @param string $sinceTimestamp - timestamp for incremental updates
     * @return array
     */
    public function getFilteredEventsTableWidget($filterId, $page, $columns = [], $timeframe = '', $sinceTimestamp = null)
    {

        if(!in_array('id', $columns)){
            $columns[] = 'id';
        }

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

        // Apply timestamp filter if provided (for incremental updates)
        if (!empty($sinceTimestamp)) {
            // Subtract small buffer to avoid missing edge-case events due to precision
            try {
                $dt = new \DateTime($sinceTimestamp, new \DateTimeZone('UTC'));
                $dt->sub(new \DateInterval('PT1S'));
                $safeTimestamp = $dt->format("Y-m-d H:i:s");
                $query->andWhere(['>', 'datetime', $safeTimestamp]);
            } catch (\Exception $e) {
                // If parsing fails, just use the timestamp as-is
                $query->andWhere(['>', 'datetime', $sinceTimestamp]);
            }
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
     * @param string $sinceTimestamp - timestamp for incremental updates
     * @return integer
     */
    public function getFilteredEventsCountForTableWidget($filterId, $timeframe = '', $sinceTimestamp = null)
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

        // Apply timestamp filter if provided (for incremental updates)
        if (!empty($sinceTimestamp)) {
            // Subtract small buffer to avoid missing edge-case events due to precision
            try {
                $dt = new \DateTime($sinceTimestamp, new \DateTimeZone('UTC'));
                $dt->sub(new \DateInterval('PT1S'));
                $safeTimestamp = $dt->format("Y-m-d H:i:s");
                $query->andWhere(['>', 'datetime', $safeTimestamp]);
            } catch (\Exception $e) {
                // If parsing fails, just use the timestamp as-is
                $query->andWhere(['>', 'datetime', $sinceTimestamp]);
            }
        }

        $result = $query->asArray()->one();
        Yii::$app->cache->flush();

        return isset($result['count']) ? intval($result['count']) : 0;
    }

    /**
     * Get filtered events grouped by country for choropleth map
     * @param integer $filterId
     * @param string $locationType - 'source' or 'destination'
     * @param string $timeframe
     * @param string $sinceTimestamp - timestamp for incremental updates
     * @return array
     */
    public function getFilteredEventsGeoMap($filterId, $locationType = 'source', $timeframe = null, $sinceTimestamp = null)
    {
        $query = SecurityEvents::find();
        
        // Select appropriate fields based on location type
        if ($locationType === 'destination') {
            $query->select([
                "COALESCE(destination_country, destination_code) as country",
                "destination_code as code",
                "count(*) as count"
            ])
            ->groupBy(["destination_code", "destination_country"])
            ->orderBy(['count' => SORT_DESC]);
        } else {
            // Default to source
            $query->select([
                "COALESCE(source_country, source_code) as country",
                "source_code as code",
                "count(*) as count"
            ])
            ->groupBy(["source_code", "source_country"])
            ->orderBy(['count' => SORT_DESC]);
        }

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

        // Apply timestamp filter if provided (for incremental updates)
        if (!empty($sinceTimestamp)) {
            // Use the timestamp as-is for consistency with other methods
            // The buffer avoids precision issues but should not exceed event interval
            $query->andWhere(['>', 'datetime', $sinceTimestamp]);
        }

        $filteredData = $query->asArray()->all();
        
        // Filter out entries with null or empty codes
        $filteredData = array_filter($filteredData, function($item) {
            return !empty($item['code']);
        });
        
        Yii::$app->cache->flush();

        return array_values($filteredData);
    }
}