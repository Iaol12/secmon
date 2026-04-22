<?php

namespace app\services;

use app\models\SecurityEvents;
use app\models\Filter;
use Yii;

class ChartDataService
{
    private const TIMEZONE = 'Europe/Bratislava';
    private const DATE_FORMAT = 'Y-m-d H:i:s';
    private const TIMESTAMP_BUFFER = 'PT1S';
    private const DEFAULT_TIMEFRAME = 'P1W';
    private const DEFAULT_GRANULARITY = '2H';

    public function getFilteredEventsPieChart($filterId, $field, $timeframe = null, $sinceTimestamp = null)
    {
        return $this->getGroupedEventsByField($filterId, $field, $timeframe, $sinceTimestamp);
    }

    public function getFilteredEventsBarChart($filterId, $field, $timeframe = null, $sinceTimestamp = null)
    {
        return $this->getGroupedEventsByField($filterId, $field, $timeframe, $sinceTimestamp);
    }

    public function getFilteredEventsLineChart($filterId, $timeframe = null, $granularity = self::DEFAULT_GRANULARITY, $sinceTimestamp = null)
    {
        $range = $this->parseTimeframeToDateInterval($timeframe ?? self::DEFAULT_TIMEFRAME);
        $interval = new \DateInterval($this->parseGranularityToDateInterval($granularity));
        $sqlFormat = $this->getSqlGroupingFormat($granularity);

        $startDate = $this->getStartDateForRange($range);
        $query = SecurityEvents::find()
            ->select(["to_char(datetime, '$sqlFormat') as x", "count(id) as y"])
            ->groupBy(["x"])
            ->orderBy(['x' => SORT_ASC])
            ->andWhere(['>', "datetime", $startDate]);

        $this->applyFilters($query, $filterId, null, $sinceTimestamp);

        $filteredData = $query->asArray()->all();
        return $this->fillLineChartData($filteredData, $startDate, $interval, $granularity);
    }

    public function getFilteredEventsTableWidget($filterId, $page, $columns = [], $timeframe = '', $sinceTimestamp = null)
    {
        if (!in_array('id', $columns)) {
            $columns[] = 'id';
        }

        $query = SecurityEvents::find();
        $page = max(1, intval($page)) - 1;

        $this->applyFilters($query, $filterId, $timeframe, $sinceTimestamp);

        if (!empty($columns) && is_array($columns)) {
            $query->select($columns);
        }

        return $query
            ->orderBy(['datetime' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(10)
            ->offset(10 * $page)
            ->asArray()
            ->all();
    }

    public function getFilteredEventsCountForTableWidget($filterId, $timeframe = '', $sinceTimestamp = null)
    {
        $query = SecurityEvents::find()
            ->select(["count(*) as count"]);

        $this->applyFilters($query, $filterId, $timeframe, $sinceTimestamp);

        $result = $query->asArray()->one();
        return isset($result['count']) ? intval($result['count']) : 0;
    }

    public function getFilteredEventsGeoMap($filterId, $locationType = 'source', $timeframe = null, $sinceTimestamp = null)
    {
        $query = SecurityEvents::find();
        $this->selectGeoFields($query, $locationType);

        $this->applyFilters($query, $filterId, $timeframe, $sinceTimestamp);

        $filteredData = $query->asArray()->all();

        return array_values(array_filter($filteredData, fn($item) => !empty($item['code'])));
    }

    public function isValidISO8601(string $granularity): bool
    {
        preg_match('/^(\d+)\s*(hour|day|week|month|year|h|m|d|w|y)s?$/i', rtrim($granularity, 's'), $matches);
        return !empty($matches);
    }

    private function getGroupedEventsByField($filterId, $field, $timeframe = null, $sinceTimestamp = null)
    {
        $query = SecurityEvents::find();
        $label = "CAST(" . $field . " AS text) as label";
        $value = "count(" . $field . ") as count";

        $query->select([$label, $value])
            ->groupBy(["label"])
            ->orderBy(['label' => SORT_ASC]);

        $this->applyFilters($query, $filterId, $timeframe, $sinceTimestamp);

        return $query->asArray()->all();
    }

    private function applyFilters(&$query, $filterId, $timeframe = null, $sinceTimestamp = null)
    {
        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (!empty($filter)) {
                $query->applyFilter($filter);
            }
        }

        if (!empty($timeframe)) {
            $startDate = $this->getStartDateForRange($this->parseTimeframeToDateInterval($timeframe));
            $query->andWhere(['>=', 'datetime', $startDate]);
        }

        if (!empty($sinceTimestamp)) {
            $safeTimestamp = $this->getSafeTimestamp($sinceTimestamp);
            $query->andWhere(['>', 'datetime', $safeTimestamp]);
        }
    }

    private function getSafeTimestamp($timestamp)
    {
        try {
            $dt = new \DateTime($timestamp, new \DateTimeZone('UTC'));
            $dt->sub(new \DateInterval(self::TIMESTAMP_BUFFER));
            return $dt->format(self::DATE_FORMAT);
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    private function getStartDateForRange($range)
    {
        $dt = new \DateTime('now', new \DateTimeZone(self::TIMEZONE));
        $dt->sub(new \DateInterval($range));
        return $dt->format(self::DATE_FORMAT);
    }

    private function fillLineChartData($filteredData, $startDate, $interval, $granularity)
    {
        $chartData = [];
        $now = new \DateTime('now', new \DateTimeZone(self::TIMEZONE));
        $dt = $this->adjustStartDateForGranularity(
            \DateTime::createFromFormat(self::DATE_FORMAT, $startDate, new \DateTimeZone(self::TIMEZONE)),
            $granularity
        );

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

    private function selectGeoFields(&$query, $locationType)
    {
        if ($locationType === 'destination') {
            $query->select([
                "COALESCE(destination_country, destination_code) as country",
                "destination_code as code",
                "count(*) as count"
            ])
            ->groupBy(["destination_code", "destination_country"])
            ->orderBy(['count' => SORT_DESC]);
        } else {
            $query->select([
                "COALESCE(source_country, source_code) as country",
                "source_code as code",
                "count(*) as count"
            ])
            ->groupBy(["source_code", "source_country"])
            ->orderBy(['count' => SORT_DESC]);
        }
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
            return self::DEFAULT_TIMEFRAME;
        }

        $amount = $matches[1];
        $unit = strtolower($matches[2]);
        $map = [
            'year' => 'P' . $amount . 'Y', 'y' => 'P' . $amount . 'Y',
            'month' => 'P' . $amount . 'M', 'm' => 'P' . $amount . 'M',
            'week' => 'P' . $amount . 'W', 'w' => 'P' . $amount . 'W',
            'day' => 'P' . $amount . 'D', 'd' => 'P' . $amount . 'D',
            'hour' => 'PT' . $amount . 'H', 'h' => 'PT' . $amount . 'H',
        ];
        return $map[$unit] ?? self::DEFAULT_TIMEFRAME;
    }

    private function getSqlGroupingFormat(string $granularity): string
    {
        $g = rtrim(strtolower($granularity), 's');
        $formats = [
            'hour' => 'YYYY-MM-DD HH24', 'h' => 'YYYY-MM-DD HH24',
            'day' => 'YYYY-MM-DD', 'd' => 'YYYY-MM-DD',
            'week' => 'YYYY-WW', 'w' => 'YYYY-WW',
            'month' => 'YYYY-MM', 'mon' => 'YYYY-MM',
            'year' => 'YYYY', 'y' => 'YYYY',
        ];

        foreach ($formats as $key => $format) {
            if (strpos($g, $key) !== false) {
                return $format;
            }
        }

        return 'YYYY-MM-DD';
    }

    private function getPhpDateFormat(string $granularity): string
    {
        $g = rtrim(strtolower($granularity), 's');
        $formats = [
            'hour' => 'Y-m-d H', 'h' => 'Y-m-d H',
            'day' => 'Y-m-d', 'd' => 'Y-m-d',
            'week' => 'Y-W', 'w' => 'Y-W',
            'month' => 'Y-m', 'mon' => 'Y-m',
            'year' => 'Y', 'y' => 'Y',
        ];

        foreach ($formats as $key => $format) {
            if (strpos($g, $key) !== false) {
                return $format;
            }
        }

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
}
