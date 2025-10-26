<?php

use yii\helpers\Html;
use yii\helpers\Url;
use yii\helpers\Json;

/* @var $this yii\web\View */
/* @var $views array */
/* @var $activeViewId int */

$this->title = 'Dashboard';

// Prepare data for React
$viewsData = [];
foreach($views as $view) {
    $components = $view->getViewComponents()->orderBy(['order' => SORT_ASC])->all();
    $componentsData = [];
    
    foreach ($components as $component) {
        $componentsData[] = [
            'id' => $component->id,
            'view_id' => $component->view_id,
            'config' => $component->config,
            'filter_id' => $component->filter_id,
            'data_type' => $component->data_type,
            'data_param' => $component->data_param,
            'order' => $component->order,
        ];
    }
    
    $viewsData[] = [
        'id' => $view->id,
        'name' => $view->name,
        'active' => $view->active,
        'refresh_time' => $view->refresh_time,
        'components' => $componentsData
    ];
}

// Get filters for the logged-in user
$loggedUserId = Yii::$app->user->getId();
$filters = \app\controllers\FilterController::getFiltersOfUser($loggedUserId);
$filtersData = [];
foreach ($filters as $filter) {
    $filtersData[] = [
        'id' => $filter->id,
        'name' => $filter->name,
    ];
}

// Get table columns
$columns = array_keys(\app\models\SecurityEvents::columns());
$tableColumns = [];
foreach ($columns as $column) {
    $tableColumns[$column] = null;
}

// Configuration for React app
$dashboardConfig = [
    'views' => $viewsData,
    'activeViewId' => $activeViewId,
    'filters' => $filtersData,
    'tableColumns' => $tableColumns,
    'urls' => [
        'changeView' => Url::to(['view/change-view']),
        'createComponent' => Url::to(['view/create-component']),
        'deleteComponent' => Url::to(['view/delete-component']),
        'updateComponent' => Url::to(['view/update-component']),
        'updateOrder' => Url::to(['view/update-order-of-components']),
        'getRefreshTimes' => Url::to(['view/get-refresh-times']),
        'updateComponentSettings' => Url::to(['filter/add-filter-to-component']),
        'deleteComponentSettings' => Url::to(['filter/remove-filter-from-component']),
        'updateComponentContent' => Url::to(['filter/get-component-content']),
    ]
];

// Register the React bundle
$this->registerJsFile('@web/js/dist/dashboard-bundle.js', [
    'depends' => 'yii\web\YiiAsset',
    'position' => \yii\web\View::POS_END
]);

// Pass configuration to JavaScript
$this->registerJs(
    'window.dashboardConfig = ' . Json::encode($dashboardConfig) . ';',
    \yii\web\View::POS_HEAD
);
?>

<div class="view-index">
    <div class="dashboard-top-actions">
        <?= Html::a(
            "<i class='material-icons'>add_to_queue</i>" . Yii::t('app', 'Create View'),
            ['create'],
            ['class' => 'btn-floating waves-effect waves-light btn-large red']
        ) ?>
        <?= Html::a(
            "<i class='material-icons'>edit</i>" . Yii::t('app', 'Update'),
            ['update', 'id' => $activeViewId],
            ['id' => 'editBtn', 'class' => 'btn-floating waves-effect waves-light btn-large blue']
        ) ?>
        <?= Html::a(
            "<i class='material-icons'>delete</i>" . Yii::t('app', 'Delete'),
            ['delete', 'id' => $activeViewId],
            [
                'id' => 'removeBtn',
                'class' => 'btn-floating waves-effect waves-light btn-large red',
                'data' => [
                    'confirm' => Yii::t('app', 'Are you sure you want to delete this dashboard?'),
                    'method' => 'post',
                ],
            ]
        ) ?>
    </div>

    <!-- React Dashboard Root -->
    <div id="react-dashboard-root"></div>

    <!-- Fallback message if JavaScript is disabled -->
    <noscript>
        <div style="padding: 40px; text-align: center; color: #f44336;">
            <h2>JavaScript is required</h2>
            <p>Please enable JavaScript to use the dashboard.</p>
        </div>
    </noscript>
</div>

<style>
.dashboard-top-actions {
    display: flex;
    gap: 16px;
    justify-content: center;
    padding: 20px 0;
    position: sticky;
    top: 0;
    z-index: 100;
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.btn-floating {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 24px;
    text-decoration: none;
    color: white;
    font-weight: 500;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.btn-floating:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.3);
}

.btn-floating.red {
    background-color: #f44336;
}

.btn-floating.blue {
    background-color: #039be5;
}

.btn-floating i {
    font-size: 20px;
}
</style>
