<?php

namespace app\controllers\api;

use Yii;
use app\models\Dashboard;
use app\models\Dashboard\DashboardWidget;
use app\models\SecurityEvents;
use app\models\Filter;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\filters\VerbFilter;
use yii\filters\ContentNegotiator;
use yii\filters\auth\HttpBearerAuth;
use yii\web\Response;
use yii\rest\Controller;
use yii\helpers\Json;
use app\services\ChartDataService;


class DashboardWidgetController extends Controller
{

    private $chartDataService;

    public function __construct($id, $module, ChartDataService $chartDataService, $config = [])
    {
        $this->chartDataService = $chartDataService;
        parent::__construct($id, $module, $config);
    }

    public function behaviors()
    {
        $behaviors = parent::behaviors();

        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];


        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];

        $behaviors['verbFilter'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'delete' => ['POST'],
            ],
        ];

        return $behaviors;
    }

    /**
     * Helper to check if the current user is authenticated.
     * @throws ForbiddenHttpException if the user is a guest.
     */
    protected function checkAccess()
    {
        if (Yii::$app->user->isGuest) {
            throw new ForbiddenHttpException('You must be logged in to access this resource.');
        }
    }

    /**
     * Get dashboard widget with filter applied content
     * @param integer $widgetId
     * @param integer $pagination
     * @return array
     */
    public function actionContent($widgetId, $pagination = 1)
    {
        $this->checkAccess();
        
        $widget = $this->findModel($widgetId);
        $this->checkWidgetOwnership($widget);

        $filter = !empty($widget->filter_id) ? Filter::findOne(['id' => $widget->filter_id]) : null;

        if (!empty($filter) && $filter->user_id != Yii::$app->user->getId()) {
            throw new ForbiddenHttpException('You do not have permission to access this filter.');
        }

        $chartType = $widget->chart_type;
        $timeframe = $widget->timeframe ?? "";

        $config = is_string($widget->config) ? Json::decode($widget->config) : $widget->config;
        switch ($chartType) {
            case "pieChart":
                // Parse config to get the field to chart
                $field = $config['pie_chart_variable'] ?? 'cef_severity';
                
                return [
                    'chartType' => $chartType,
                    'timeframe' => $timeframe,
                    'data' => $this->chartDataService->getFilteredEventsPieChart($widget->filter_id, $field)
                ];
                
            case "barChart":
                // Parse config to get the field to chart
                $field = $config['bar_chart_variable'] ?? 'cef_severity';
                
                return [
                    'chartType' => $chartType,
                    'timeframe' => $timeframe,
                    'data' => $this->chartDataService->getFilteredEventsBarChart($widget->filter_id, $field)
                ];
                
            case "lineChart":
                $granularity = $config['granularity'] ?? '6H';
                return [
                    'chartType' => $chartType,
                    'timeframe' => $timeframe,
                    'data' => $this->chartDataService->getFilteredEventsLineChart($widget->filter_id, $timeframe, $granularity)
                ];
                
            case "table":
                $config = is_string($widget->config) ? Json::decode($widget->config) : $widget->config;
                $columns = $config['columns'] ?? ['id', 'datetime', 'device_host_name', 'application_protocol'];
                
                $filteredData = $this->chartDataService->getFilteredEvents($widget->filter_id, $pagination);
                $count = $this->chartDataService->getFilteredEventsCount($widget->filter_id);

                return [
                    'chartType' => $chartType,
                    'timeframe' => $timeframe,
                    'pagination' => [
                        'page' => $pagination,
                        'total' => $count,
                    ],
                    'columns' => $columns,
                    'data' => $filteredData,
                ];
                
            default:
                throw new \yii\web\BadRequestHttpException('Invalid chart type.');
        }
    }

    public function actionAllSecurityEventFields()
    {
        return [
            'fields' => array_keys(\app\models\SecurityEvents::columns())
        ];
    }

    /**
     * Update dashboard widget filter and configuration
     * @param integer $widgetId
     * @return array
     */
    public function actionUpdateSettings()
    {
        $widgetId = Yii::$app->request->post('widget_id');

        $this->checkAccess();
        $widget = $this->findModel($widgetId);
        $this->checkWidgetOwnership($widget);


        $title = Yii::$app->request->post('title');
        $chartType = Yii::$app->request->post('chart_type');
        $dashboardId = Yii::$app->request->post('dashboard_id');
        $filterId = Yii::$app->request->post('filter_id');
        $timeframe = Yii::$app->request->post('timeframe');
        $config = Yii::$app->request->post('config');

        if (!empty($filterId)) {
            $filter = Filter::findOne(['id' => $filterId]);
            if (empty($filter) || $filter->user_id != Yii::$app->user->getId()) {
                throw new ForbiddenHttpException('You do not have permission to use this filter.');
            }
            $widget->filter_id = $filterId;
        }
        
        if (!empty($title)) {
            $widget->title = $title;
        }

        if (!empty($chartType)) {
            $widget->chart_type = $chartType;
        }

        if (!empty($dashboardId)) {
            $dashboard = Dashboard::findOne(['id' => $dashboardId]);
            if (empty($dashboard) || $dashboard->user_id != Yii::$app->user->getId()) {
                throw new ForbiddenHttpException('You do not have permission to use this dashboard.');
            }
            $widget->dashboard_id = $dashboardId;
        }

        if (!empty($timeframe)) {
            $widget->timeframe = $timeframe;
        }

        if (!empty($config)) {
            // Validate config based on chart type
            $configArray = is_string($config) ? Json::decode($config) : $config;
            $dbCols = array_keys(SecurityEvents::columns()); 
            if ($chartType == 'table') {
                if (!empty($configArray['table_columns'])) {
                    $validCols = [];
                    foreach ($configArray['table_columns'] as $col) {
                        if (in_array($col, $dbCols)) {
                            $validCols[] = $col;
                        }
                    }
                    $configArray['table_columns'] = $validCols;
                    $config = $configArray;
                }
            }
            elseif($chartType == 'pieChart') {
                if (!empty($configArray['pie_chart_variable']) && in_array($configArray['pie_chart_variable'], $dbCols)) {
                    $config = $configArray;
                } else {
                    throw new \yii\web\BadRequestHttpException('Invalid variable for pie chart configuration.');
                }
            }
            elseif($chartType == 'barChart') {
                if (!empty($configArray['bar_chart_variable']) && in_array($configArray['bar_chart_variable'], $dbCols)) {
                    $config = $configArray;
                } else {
                    throw new \yii\web\BadRequestHttpException('Invalid variable for bar chart configuration.');
                }
            }
            elseif($chartType == 'lineChart') {
                if (!empty($configArray['granularity']) && $this->chartDataService->isValidISO8601($configArray['granularity'])) {
                    $config = $configArray;
                } else {
                    throw new \yii\web\BadRequestHttpException('Invalid time granularity for line chart configuration.');
                }
            }

            $widget->config = is_array($config) ? Json::encode($config) : $config;
        }

        if ($widget->save()) {
            return [
                'success' => true,
                'widget' => $widget,
            ];
        }

        return [
            'success' => false,
            'errors' => $widget->errors,
        ];
    }

    /**
     * Determine appropriate time unit for aggregation
     * @param Filter $timeFilter
     * @return string
     */
    protected function determineTimeUnit($timeFilter)
    {
        // Implementation would depend on your time filter structure
        // For now, return a sensible default
        return 'hour';
    }

    /**
     * Check if the current user owns the dashboard that contains this widget
     * @param DashboardWidget $widget
     * @throws ForbiddenHttpException
     */
    protected function checkWidgetOwnership($widget)
    {
        $dashboard = Dashboard::findOne(['id' => $widget->dashboard_id]);
        
        if (empty($dashboard) || $dashboard->user_id != Yii::$app->user->getId()) {
            throw new ForbiddenHttpException('You do not have permission to access this widget.');
        }
    }

    /**
     * Finds the DashboardWidget model based on its primary key value.
     * @param integer $id
     * @return DashboardWidget the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = DashboardWidget::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested dashboard widget does not exist.');
    }
}