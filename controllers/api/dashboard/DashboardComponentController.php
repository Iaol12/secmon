<?php

namespace app\controllers\api\dashboard;

use Yii;
use app\models\Dashboard;
use app\models\Dashboard\DashboardComponent;
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


class DashboardComponentController extends Controller
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
     * Get dashboard component with filter applied content
     * @param integer $componentId
     * @param integer $pagination
     * @return array
     */
    public function actionGetContent($componentId, $pagination = 1)
    {
        $this->checkAccess();
        
        $component = $this->findModel($componentId);
        $this->checkComponentOwnership($component);

        $filter = !empty($component->filter_id) ? Filter::findOne(['id' => $component->filter_id]) : null;

        if (!empty($filter) && $filter->user_id != Yii::$app->user->getId()) {
            throw new ForbiddenHttpException('You do not have permission to access this filter.');
        }

        $chartType = $component->chart_type;
        $timeframe = $component->timeframe ?? "";

        switch ($chartType) {
            case "pieChart":
                // Parse config to get the field to chart
                $config = is_string($component->config) ? Json::decode($component->config) : $component->config;
                $field = $config['field'] ?? 'cef_severity';
                
                return [
                    'chartType' => $chartType,
                    'timeframe' => $timeframe,
                    'data' => $this->chartDataService->getFilteredEventsPieChart($component->filter_id, $field)
                ];
                
            case "barChart":
                return [
                    'chartType' => $chartType,
                    'timeframe' => $timeframe,
                    'data' => $this->chartDataService->getFilteredEventsBarChart($component->filter_id, $timeframe)
                ];
                
            case "table":
                $config = is_string($component->config) ? Json::decode($component->config) : $component->config;
                $columns = $config['columns'] ?? ['id', 'datetime', 'device_host_name', 'application_protocol'];
                
                $filteredData = $this->chartDataService->getFilteredEvents($component->filter_id, $pagination);
                $count = $this->chartDataService->getFilteredEventsCount($component->filter_id);

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
     * Update dashboard component filter and configuration
     * @param integer $componentId
     * @return array
     */
    public function actionUpdateSettings()
    {
        $componentId = Yii::$app->request->post('component_id');

        $this->checkAccess();
        $component = $this->findModel($componentId);
        $this->checkComponentOwnership($component);


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
            $component->filter_id = $filterId;
        }
        
        if (!empty($title)) {
            $component->title = $title;
        }

        if (!empty($chartType)) {
            $component->chart_type = $chartType;
        }

        if (!empty($dashboardId)) {
            $dashboard = Dashboard::findOne(['id' => $dashboardId]);
            if (empty($dashboard) || $dashboard->user_id != Yii::$app->user->getId()) {
                throw new ForbiddenHttpException('You do not have permission to use this dashboard.');
            }
            $component->dashboard_id = $dashboardId;
        }

        if (!empty($timeframe)) {
            $component->timeframe = $timeframe;
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

            $component->config = is_array($config) ? Json::encode($config) : $config;
        }

        if ($component->save()) {
            return [
                'success' => true,
                'component' => $component,
            ];
        }

        return [
            'success' => false,
            'errors' => $component->errors,
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
     * Check if the current user owns the dashboard that contains this component
     * @param DashboardComponent $component
     * @throws ForbiddenHttpException
     */
    protected function checkComponentOwnership($component)
    {
        $dashboard = Dashboard::findOne(['id' => $component->dashboard_id]);
        
        if (empty($dashboard) || $dashboard->user_id != Yii::$app->user->getId()) {
            throw new ForbiddenHttpException('You do not have permission to access this component.');
        }
    }

    /**
     * Finds the DashboardComponent model based on its primary key value.
     * @param integer $id
     * @return DashboardComponent the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = DashboardComponent::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested dashboard component does not exist.');
    }
}