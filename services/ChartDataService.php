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

    public function getFilteredEventsLineChart($filterId, $timeframe = null)
    {
        $range = 'P1D';

        if (!empty($timeframe)) {
            $timeUnit = substr($timeframe, -1);
            if ($timeUnit == 'Y' || $timeUnit == 'M' || $timeUnit == 'W' || $timeUnit == 'D') {
                $range = 'P' . $timeframe;
            } else if ($timeUnit == 'm') {
                $range = 'PT' . substr($timeframe, 0, -1) . 'M';
            } else {
                $range = 'PT' . $timeframe;
            }
        }

        $dt = new \DateTime();
        $dt->setTimezone(new \DateTimeZone('Europe/Bratislava'));
        $dt->sub(new \DateInterval($range));
        $date = date_format($dt, "Y-m-d H:i:s");

        $query = SecurityEvents::find();
        $query->select(["to_char(datetime,'YYYY-DD-MM HH24:00') as x", "count(to_char(datetime,'HH24 MM-DD-YYYY')) as y"])
            ->groupBy(["x"])
            ->orderBy(['x' => SORT_ASC])
            ->andWhere(['>', "datetime", $date]);

        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (!empty($filter)) {
                $query->applyFilter($filter);
            }
        }

        $filteredData = $query->asArray()->all();
        Yii::$app->cache->flush();
        
        $chartData = array();
        $now = new \DateTime();
        $i = 0;
        
        while ($dt <= $now) {
            $str = date_format($dt, 'Y-d-m H:00');
            $formatted = date_format($dt, 'H:00 m-d');

            if (count($filteredData) > 0 && isset($filteredData[$i]) && strcmp($str, $filteredData[$i]['x']) === 0) {
                $chartData[] = ['x' => $formatted, 'y' => intval($filteredData[$i]['y'])];
                $i++;
            } else {
                $chartData[] = ['x' => $formatted, 'y' => 0];
            }

            $dt->add(new \DateInterval('PT1H'));
        }

        return $chartData;
    }

    public function getFilteredEvents($filterId, $page)
    {
        $query = SecurityEvents::find();
        $page = max(1, intval($page)) - 1;

        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (!empty($filter)) {
                $query->applyFilter($filter);
            }
        }

        $filteredData = $query
            ->orderBy(['datetime' => SORT_DESC, 'id' => SORT_DESC])
            ->limit(10)
            ->offset(10 * $page)
            ->all();

        Yii::$app->cache->flush();

        return $filteredData;
    }

    public function getFilteredEventsCount($filterId)
    {
        $query = SecurityEvents::find();
        $query->select(["count(*) as count"]);

        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (!empty($filter)) {
                $query->applyFilter($filter);
            }
        }

        $result = $query->asArray()->one();
        Yii::$app->cache->flush();

        return isset($result['count']) ? intval($result['count']) : 0;
    }

}