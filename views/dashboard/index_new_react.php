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
foreach ($views as $view) {
    $components = $view->getViewComponents()->orderBy(['order' => SORT_ASC])->all();
    $componentsData = [];
    
    foreach ($components as $component) {
        $componentsData[] = [
            'id' => $component->id,
            'view_id' => $component->view_id,
            'config' => $component->config,
            'order' => $component->order,
            'filter_id' => $component->filter_id,
            'data_type' => $component->data_type,
            'data_param' => $component->data_param,
        ];
    }
    
    $viewsData[] = [
        'id' => $view->id,
        'name' => $view->name,
        'active' => $view->active,
        'refresh_time' => $view->refresh_time,
        'components' => $componentsData,
    ];
}

// Get filters
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
foreach ($columns as $col) {
    $tableColumns[$col] = $col;
}

// Register React bundle
$this->registerJsFile('@web/js/dist/dashboard-bundle.js', [
    'depends' => 'yii\web\YiiAsset',
    'position' => \yii\web\View::POS_END
]);

// Pass configuration to JavaScript
$dashboardConfig = [
    'views' => $viewsData,
    'activeViewId' => (string)$activeViewId,
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

$this->registerJs(
    'window.dashboardConfig = ' . Json::encode($dashboardConfig) . ';',
    \yii\web\View::POS_HEAD
);
?>

<div class="view-index">
    <!-- Main actions for dashboard management -->
    <div class="main-actions centered-horizontal" style="margin-bottom: 20px;">
        <?= Html::a("<i class='material-icons'>add_to_queue</i>" . Yii::t('app', 'Create View'), 
            ['create'], 
            ['class' => 'btn-floating waves-effect waves-light btn-large red']) ?>
        <?= Html::a("<i class='material-icons'>edit</i>" . Yii::t('app', 'Update'), 
            ['update', 'id' => $activeViewId], 
            ['id' => 'editBtn', 'class' => 'btn-floating waves-effect waves-light btn-large blue']) ?>
        <?= Html::a("<i class='material-icons'>delete</i>" . Yii::t('app', 'Delete'), 
            ['delete', 'id' => $activeViewId],
            [
                'id' => 'removeBtn',
                'class' => 'btn-floating waves-effect waves-light btn-large red',
                'data' => [
                    'confirm' => Yii::t('app', 'Are you sure you want to delete this dashboard?'),
                    'method' => 'post',
                ],
            ]) ?>
    </div>

    <!-- React Dashboard Root -->
    <div id="react-dashboard-root"></div>

    <!-- Fallback for browsers without JavaScript -->
    <noscript>
        <div style="padding: 40px; text-align: center; background: #fff3cd; border: 1px solid #ffc107; border-radius: 4px; margin: 20px;">
            <h3>JavaScript Required</h3>
            <p>This dashboard requires JavaScript to be enabled. Please enable JavaScript in your browser settings.</p>
        </div>
    </noscript>
</div>

<?php
// Update the edit button URL when view changes via JavaScript
$this->registerJs("
    // Listen for view changes from React
    window.addEventListener('dashboardViewChanged', function(e) {
        const viewId = e.detail.viewId;
        const editBtn = document.getElementById('editBtn');
        const removeBtn = document.getElementById('removeBtn');
        
        if (editBtn) {
            editBtn.href = editBtn.href.replace(/id=\d+/, 'id=' + viewId);
        }
        if (removeBtn) {
            removeBtn.href = removeBtn.href.replace(/id=\d+/, 'id=' + viewId);
        }
    });
", \yii\web\View::POS_READY);
?>
