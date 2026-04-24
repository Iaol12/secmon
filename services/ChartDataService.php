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
        $query->select([
            "CAST($field AS text) as label",
            "count($field) as count",
        ])
            ->groupBy(['label'])
            ->orderBy(['label' => SORT_ASC]);

        $this->applyFilterIfPresent($query, $filterId);
        $this->applyTimeframeFilter($query, $timeframe);
        $this->applySinceTimestampFilter($query, $sinceTimestamp);

        $filteredData = $query->asArray()->all();
        Yii::$app->cache->flush();

        return $filteredData;
    }

    public function getFilteredEventsBarChart($filterId, $field, $timeframe = null, $sinceTimestamp = null)
    {
        $query = SecurityEvents::find();
        $query->select([
            "CAST($field AS text) as label",
            "count($field) as count",
        ])
            ->groupBy([$field])
            ->orderBy(['label' => SORT_ASC]);

        $this->applyFilterIfPresent($query, $filterId);
        $this->applyTimeframeFilter($query, $timeframe);
        $this->applySinceTimestampFilter($query, $sinceTimestamp);

        $filteredData = $query->asArray()->all();
        Yii::$app->cache->flush();

        return $filteredData;
    }

    public function getFilteredEventsLineChart($filterId, $timeframe = null, $granularity = '2H', $sinceTimestamp = null)
    {
        $range = !empty($timeframe) ? $this->parseTimeframeToDateInterval($timeframe) : 'P1W';
        $interval = new \DateInterval($this->parseGranularityToDateInterval($granularity));
        $sqlGroupingFormat = $this->getSqlGroupingFormat($granularity);

        $startDate = $this->getBratislavaNow();
        $startDate->sub(new \DateInterval($range));
        $startDateString = $startDate->format('Y-m-d H:i:s');

        $query = SecurityEvents::find()
            ->select(["to_char(datetime, '$sqlGroupingFormat') as x", "count(id) as y"])
            ->groupBy(["x"])
            ->orderBy(['x' => SORT_ASC])
            ->andWhere(['>', 'datetime', $startDateString]);

        $this->applyFilterIfPresent($query, $filterId);
        $this->applySinceTimestampFilter($query, $sinceTimestamp);

        $filteredData = $query->asArray()->all();
        $chartData = [];
        $now = $this->getBratislavaNow();
        $current = $this->anchorToIntervalBoundary($startDate, $granularity);
        $phpDateFormat = $this->getPhpDateFormat($granularity);

        for ($index = 0; $current <= $now; $current->add($interval)) {
            $currentEnd = clone $current;
            $currentEnd->add($interval);
            $currentEndString = $currentEnd->format($phpDateFormat);
            $currentValue = 0;

            while (isset($filteredData[$index]) && strcmp($filteredData[$index]['x'], $currentEndString) < 0) {
                $currentValue += (int) $filteredData[$index]['y'];
                $index++;
            }

            $chartData[] = ['x' => $current->format('d.m.Y G:i'), 'y' => $currentValue];
        }

        return $chartData;
    }

    public function getFilteredEventsTableWidget($filterId, $page, $columns = [], $timeframe = '', $sinceTimestamp = null)
    {
        if (!in_array('id', $columns, true)) {
            $columns[] = 'id';
        }

        $query = SecurityEvents::find();
        $page = max(1, intval($page)) - 1;

        if (!empty($columns) && is_array($columns)) {
            $query->select($columns);
        }

        $this->applyFilterIfPresent($query, $filterId);
        $this->applyTimeframeFilter($query, $timeframe);
        $this->applySinceTimestampFilter($query, $sinceTimestamp);

        $filteredData = $query
            ->orderBy(['datetime' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(10)
            ->offset(10 * $page)
            ->asArray()
            ->all();

        Yii::$app->cache->flush();

        return $filteredData;
    }

    public function getFilteredEventsGeoMap($filterId, $locationType = 'source', $timeframe = null, $sinceTimestamp = null)
    {
        $query = SecurityEvents::find();

        if ($locationType === 'destination') {
            $query->select([
                'COALESCE(destination_country, destination_code) as country',
                'destination_code as code',
                'count(*) as count',
            ])
            ->groupBy(['destination_code', 'destination_country'])
            ->orderBy(['count' => SORT_DESC]);
        } else {
            $query->select([
                'COALESCE(source_country, source_code) as country',
                'source_code as code',
                'count(*) as count',
            ])
            ->groupBy(['source_code', 'source_country'])
            ->orderBy(['count' => SORT_DESC]);
        }

        $this->applyFilterIfPresent($query, $filterId);
        $this->applyTimeframeFilter($query, $timeframe);
        $this->applySinceTimestampFilter($query, $sinceTimestamp);

        $filteredData = $query->asArray()->all();

        $filteredData = array_filter($filteredData, function ($item) {
            return !empty($item['code']);
        });
        
        Yii::$app->cache->flush();

        return array_values($filteredData);
    }

    public function getFilteredEventsCountForTableWidget($filterId, $timeframe = '', $sinceTimestamp = null)
    {
        $query = SecurityEvents::find();
        $query->select(['count(*) as count']);

        $this->applyFilterIfPresent($query, $filterId);
        $this->applyTimeframeFilter($query, $timeframe);
        $this->applySinceTimestampFilter($query, $sinceTimestamp);

        $result = $query->asArray()->one();
        Yii::$app->cache->flush();

        return isset($result['count']) ? intval($result['count']) : 0;
    }

    private function applyFilterIfPresent($query, $filterId): void
    {
        if (empty($filterId)) {
            return;
        }

        $filter = Filter::findOne(['id' => $filterId]);

        if (!empty($filter)) {
            $query->applyFilter($filter);
        }
    }

    private function applyTimeframeFilter($query, $timeframe): void
    {
        if (empty($timeframe)) {
            return;
        }

        $startDate = $this->getBratislavaNow();
        $startDate->sub(new \DateInterval($this->parseTimeframeToDateInterval($timeframe)));
        $query->andWhere(['>=', 'datetime', $startDate->format('Y-m-d H:i:s')]);
    }

    private function applySinceTimestampFilter($query, $sinceTimestamp): void
    {
        if (empty($sinceTimestamp)) {
            return;
        }
        $query->andWhere(['>', 'datetime', $sinceTimestamp]);
        return;
    }

    private function getBratislavaNow(): \DateTime
    {
        return new \DateTime('now', new \DateTimeZone('Europe/Bratislava'));
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
        if (empty($matches)) {
            return 'P1W';
        }

        $amount = $matches[1];
        $unit = strtolower($matches[2]);
        $map = [
            'year' => 'P' . $amount . 'Y',
            'y' => 'P' . $amount . 'Y',
            'month' => 'P' . $amount . 'M',
            'm' => 'P' . $amount . 'M',
            'week' => 'P' . $amount . 'W',
            'w' => 'P' . $amount . 'W',
            'day' => 'P' . $amount . 'D',
            'd' => 'P' . $amount . 'D',
            'hour' => 'PT' . $amount . 'H',
            'h' => 'PT' . $amount . 'H',
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
        if (strpos($g, 'hour') !== false || strpos($g, 'h') !== false) {
            return 'YYYY-MM-DD HH24';
        }

        if (strpos($g, 'day') !== false || strpos($g, 'd') !== false) {
            return 'YYYY-MM-DD';
        }

        if (strpos($g, 'week') !== false || strpos($g, 'w') !== false) {
            return 'YYYY-WW';
        }

        if (strpos($g, 'month') !== false || strpos($g, 'mon') !== false) {
            return 'YYYY-MM';
        }

        if (strpos($g, 'year') !== false || strpos($g, 'y') !== false) {
            return 'YYYY';
        }

        return 'YYYY-MM-DD';
    }

    private function getPhpDateFormat(string $granularity): string
    {
        $g = rtrim(strtolower($granularity), 's');
        if (strpos($g, 'hour') !== false || strpos($g, 'h') !== false) {
            return 'Y-m-d H';
        }

        if (strpos($g, 'day') !== false || strpos($g, 'd') !== false) {
            return 'Y-m-d';
        }

        if (strpos($g, 'week') !== false || strpos($g, 'w') !== false) {
            return 'Y-W';
        }

        if (strpos($g, 'month') !== false || strpos($g, 'mon') !== false) {
            return 'Y-m';
        }

        if (strpos($g, 'year') !== false || strpos($g, 'y') !== false) {
            return 'Y';
        }

        return 'Y-m-d';
    }
    private function anchorToIntervalBoundary(\DateTime $dt, string $granularity): \DateTime
    {
        $g = rtrim(strtolower($granularity), 's');
        $anchored = clone $dt;

        if (strpos($g, 'year') !== false || strpos($g, 'y') !== false) {
            $anchored->setDate($anchored->format('Y'), 1, 1);
            $anchored->setTime(0, 0, 0);
        } elseif (strpos($g, 'month') !== false || strpos($g, 'mon') !== false) {
            $anchored->setDate($anchored->format('Y'), $anchored->format('m'), 1);
            $anchored->setTime(0, 0, 0);
        } elseif (strpos($g, 'week') !== false || strpos($g, 'w') !== false) {
            $anchored->setISODate($anchored->format('Y'), $anchored->format('W'), 1);
            $anchored->setTime(0, 0, 0);
        } elseif (strpos($g, 'day') !== false || strpos($g, 'd') !== false) {
            $anchored->setTime(0, 0, 0);
        } elseif (strpos($g, 'hour') !== false || strpos($g, 'h') !== false) {
            preg_match('/^(\d+)\s*h/i', $g, $matches);
            if (!empty($matches[1])) {
                $hours = (int) $matches[1];
                $currentHour = (int) $anchored->format('H');
                $boundaryHour = (int) floor($currentHour / $hours) * $hours;
                $anchored->setTime($boundaryHour, 0, 0);
            } else {
                $anchored->setTime((int) $anchored->format('H'), 0, 0);
            }
        }

        return $anchored;
    }
}