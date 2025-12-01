<?php

namespace app\controllers\api;

use Yii;
use app\models\Dashboard;
use app\models\Dashboard\Component;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\filters\VerbFilter;
use yii\filters\ContentNegotiator;
use yii\filters\auth\HttpBearerAuth;
use yii\web\Response;
use yii\rest\Controller;


class DashboardController extends Controller
{
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
                'dashboards' => ['GET'],
                'view' => ['GET'],
                'create' => ['POST'],
                'update' => ['PUT', 'PATCH'],
                'delete' => ['DELETE'],
                'change-active' => ['POST'],
                'create-component' => ['POST'],
                'update-component' => ['PUT', 'PATCH'],
                'delete-component' => ['DELETE'],
                'get-refresh-times' => ['GET'],
                'update-component-order' => ['POST'],
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
     * Retrieves all Dashboards for the current user.
     * If no dashboards exist, a default one is created.
     *
     * @return array|Dashboard[] The list of Dashboard models.
     * @throws ForbiddenHttpException if not authenticated.
     */
    public function actionDashboards()
    {
        $this->checkAccess();

        $userId = Yii::$app->user->getId();
        $dashboards = Dashboard::findAll(['user_id' => $userId]);

        // If no dashboard exists, create a default one
        if (empty($dashboards)) {
            $dashboard = new Dashboard();
            $dashboard->name = 'Default Dashboard';
            $dashboard->user_id = $userId;
            $dashboard->active = 1;
            $dashboard->refresh_time = '10S';
            if ($dashboard->save()) {
                $dashboards[] = $dashboard;
            } else {
                Yii::error('Failed to create default dashboard: ' . print_r($dashboard->errors, true));
            }
        }
            $safeDashboards = array_map(function (Dashboard $dashboard) {
            $dashboardArray = $dashboard->toArray();
            unset($dashboardArray['user_id']);
            return $dashboardArray;
        }, $dashboards);

        return $safeDashboards;
    }

    /**
     * Returns a single Dashboard model by ID.
     *
     * @param int $id The ID of the dashboard.
     * @return Dashboard The loaded dashboard model.
     * @throws NotFoundHttpException if the dashboard does not exist.
     * @throws ForbiddenHttpException if not authenticated or not owned by user.
     */
    public function actionDashboard($id)
    {
        $this->checkAccess();
        return $this->findModel($id);
    }

    /**
     * Creates a new Dashboard model.
     *
     * @return Dashboard|array The created Dashboard model or validation errors.
     * @throws ForbiddenHttpException if not authenticated.
     */
    public function actionCreate()
    {
        $this->checkAccess();

        $model = new Dashboard();
        $model->user_id = Yii::$app->user->getId();

        // The old controller logic handled setting the new one as active and deactivating others.
        // We will keep this logic here for consistency.

        // Deactivate all current active dashboards for the user
        Dashboard::updateAll(['active' => 0], ['user_id' => $model->user_id, 'active' => 1]);

        // Load POST data, set new dashboard as active
        $model->load(Yii::$app->request->post(), '');
        $model->active = 1; // Set new one as active

        if ($model->save()) {
            Yii::$app->response->statusCode = 201; // Created
            return $model;
        }

        // Return errors on failure
        Yii::$app->response->statusCode = 422; // Unprocessable Entity
        return $model->errors;
    }

    /**
     * Updates an existing Dashboard model.
     *
     * @param int $id The ID of the dashboard.
     * @return Dashboard|array The updated Dashboard model or validation errors.
     * @throws NotFoundHttpException if the dashboard is not found.
     * @throws ForbiddenHttpException if not authenticated or not owned by user.
     */
    public function actionUpdate($id)
    {
        $this->checkAccess();

        $model = $this->findModel($id);

        $model->active = 1;
        $model->load(Yii::$app->request->post(), '');

        if ($model->save()) {
            return $model;
        }

        // Return errors on failure
        Yii::$app->response->statusCode = 422; // Unprocessable Entity
        return $model->errors;
    }

    /**
     * Deletes an existing Dashboard model.
     *
     * @param int $id The ID of the dashboard.
     * @return bool True on successful deletion.
     * @throws NotFoundHttpException if the dashboard is not found.
     * @throws ForbiddenHttpException if not authenticated or not owned by user.
     */
    public function actionDelete($id)
    {
        $this->checkAccess();
        $model = $this->findModel($id); // This checks ownership

        if ($model->delete()) {
            Yii::$app->response->statusCode = 204; // No Content
            return true;
        }
        // Should rarely happen if findModel and delete() work correctly, but good practice.
        Yii::$app->response->statusCode = 500;
        return false;
    }


    public function actionChangeActive()
    {
        // 1. Get the parameter from the POST body (raw data)
        $newDashboardId = Yii::$app->request->post('newDashboardId');

        if (!$newDashboardId) {
            // Throw a bad request exception if the ID is missing
            throw new \yii\web\BadRequestHttpException('Missing newDashboardId parameter in POST request.');
        }
        $this->checkAccess();
        $userId = Yii::$app->user->getId();
        Dashboard::updateAll(['active' => 0], ['user_id' => $userId]);
        $dashboard = $this->findModel($newDashboardId); 
        // Activate the requested dashboard
        $dashboard->active = 1;
        $dashboard->save(false); 

        return $this->getComponentsOfDashboard($newDashboardId);
    }

    /**
     * Creates a new Component for a specific Dashboard.
     *
     * Endpoint: POST /dashboards/create-component
     * Body: { "dashboard_id": 1, "config": "{...}", "order": 1 }
     *
     * NOTE: The original action accepted parameters via query string, REST prefers body data.
     * This implementation uses body data for config and order, and assumes dashboard_id is also in the body or route.
     *
     * @return array|bool The created component model or validation errors.
     * @throws ForbiddenHttpException if not authenticated.
     */
    public function actionCreateComponent()
    {
        $this->checkAccess();

        $request = Yii::$app->request;
        $dashboardId = $request->post('dashboard_id');
        $config = $request->post('config');
        $order = $request->post('order');

        // Verify the dashboard exists and belongs to the user
        $this->findModel($dashboardId);

        $component = new Component();
        $component->dashboard_id = $dashboardId;
        $component->config = $config;
        $component->order = $order;

        if ($component->save()) {
            Yii::$app->response->statusCode = 201; // Created
            // Note: Returning the full widget HTML is non-RESTful, but kept for compatibility with the old controller's intended usage.
            // A pure API would just return the component model.
            return [
                'component' => $component,
                // 'html' => \app\widgets\ComponentWidget::widget(['data' => compact('component')]), // Removed to keep it RESTful, return data only.
                'id' => $component->id,
            ];
        }

        Yii::$app->response->statusCode = 422;
        return $component->errors;
    }

    /**
     * Updates an existing Component.
     *
     * Endpoint: PUT /dashboards/update-component/{componentId}
     * Body: { "config": "{...}" }
     *
     * @param int $componentId The ID of the component to update.
     * @return Component|array The updated component or validation errors.
     * @throws NotFoundHttpException if the component is not found.
     * @throws ForbiddenHttpException if not authenticated.
     */
    public function actionUpdateComponent($componentId)
    {
        $this->checkAccess();
        $request = Yii::$app->request;

        $component = Component::findOne($componentId);

        if ($component === null) {
            throw new NotFoundHttpException('The requested component does not exist.');
        }

        // Basic check: ensure component's parent dashboard belongs to the user
        $this->findModel($component->dashboard_id);

        $component->config = $request->getBodyParam('config');

        if ($component->save()) {
            return $component;
        }

        Yii::$app->response->statusCode = 422;
        return $component->errors;
    }

    /**
     * Deletes an existing Component.
     *
     * Endpoint: DELETE /dashboards/delete-component/{componentId}
     *
     * @param int $componentId The ID of the component to delete.
     * @return bool True on successful deletion.
     * @throws NotFoundHttpException if the component is not found.
     * @throws ForbiddenHttpException if not authenticated.
     */
    public function actionDeleteComponent($componentId)
    {
        $this->checkAccess();

        $component = Component::findOne($componentId);

        if ($component === null) {
            throw new NotFoundHttpException('The requested component does not exist.');
        }

        // Basic check: ensure component's parent dashboard belongs to the user
        $this->findModel($component->dashboard_id);

        if ($component->delete()) {
            Yii::$app->response->statusCode = 204; // No Content
            return true;
        }

        Yii::$app->response->statusCode = 500;
        return false;
    }

    /**
     * Updates order of components in a dashboard.
     *
     * Endpoint: POST /dashboards/update-component-order/{dashboardId}
     * Body: [{ "id": 1, "order": 1 }, { "id": 2, "order": 2 }, ...]
     *
     * @param int $dashboardId ID of the dashboard to update.
     * @return bool True on successful update.
     * @throws NotFoundHttpException if the dashboard is not found.
     * @throws ForbiddenHttpException if not authenticated or not owned by user.
     */
    public function actionUpdateComponentOrder($dashboardId)
    {
        $this->checkAccess();
        $loggedUserId = Yii::$app->user->getId();

        // Check dashboard existence and ownership
        $this->findModel($dashboardId);

        $request = Yii::$app->request;
        // Expects a JSON array in the request body
        $componentOrder = $request->getBodyParam('componentOrder'); // Assuming body contains an array under key 'componentOrder'

        if (!is_array($componentOrder)) {
            $componentOrder = $request->getBodyParam('0'); // Fallback for simple array body
            if (!is_array($componentOrder)) {
                 throw new \yii\web\BadRequestHttpException('Invalid component order data provided.');
            }
        }

        foreach ($componentOrder as $value) {
            if (isset($value['id']) && isset($value['order'])) {
                $component = Component::findOne(['id' => $value['id'], 'dashboard_id' => $dashboardId]);

                if (!empty($component)) {
                    $component->order = (int) $value['order'];
                    // Using save(false) to skip validation and directly update
                    if (!$component->save(false)) {
                        Yii::warning("Failed to update component order for ID {$value['id']}: " . print_r($component->errors, true));
                    }
                }
            }
        }

        return true;
    }

    /**
     * Finds the Dashboard model based on its primary key value and checks ownership.
     *
     * @param int $id
     * @return Dashboard The loaded model
     * @throws NotFoundHttpException if the model cannot be found or does not belong to the user.
     */
    protected function findModel($id)
    {
        $model = Dashboard::findOne($id);
        $userId = Yii::$app->user->getId();

        if ($model !== null) {
            // Check ownership
            if ($model->user_id == $userId) {
                return $model;
            } else {
                throw new ForbiddenHttpException('You do not have permission to access this dashboard.');
            }
        } else {
            throw new NotFoundHttpException("The requested dashboard with ID {$id} does not exist.");
        }
    }

    /**
     * Gets all components associated with a Dashboard.
     *
     * @param int $dashboardId The ID of the dashboard.
     * @return array|Component[] The list of components.
     * @throws NotFoundHttpException if the dashboard does not exist or does not belong to the user.
     */
    protected function getComponentsOfDashboard($dashboardId)
    {
        // findModel will throw an exception if the dashboard doesn't exist or doesn't belong to the user
        $this->findModel($dashboardId);

        return Component::findAll(['dashboard_id' => $dashboardId]);
    }
}