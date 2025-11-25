<?php

namespace app\controllers\api;

use Yii;
use app\models\Dashboard; // Assuming 'View' model was renamed to 'Dashboard'
use app\models\Dashboard\Component; // Assuming View\Component was renamed to Dashboard\Component
use yii\rest\Controller;
use yii\web\NotFoundHttpException;
use yii\web\ForbiddenHttpException;
use yii\filters\VerbFilter;
use yii\filters\ContentNegotiator;
use yii\filters\auth\HttpBearerAuth;
use yii\web\Response;

/**
 * DashboardController implements the CRUD actions for the Dashboard (formerly View) model as a REST API.
 * The model name is assumed to be 'Dashboard' now, located at app\models\Dashboard.
 */
class DashboardController extends Controller
{
    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();

        // Use ContentNegotiator to ensure all responses are JSON
        $behaviors['contentNegotiator'] = [
            'class' => ContentNegotiator::class,
            'formats' => [
                'application/json' => Response::FORMAT_JSON,
            ],
        ];

        // Use HttpBearerAuth for stateless token authentication
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
            // Only 'options' action is excluded from authentication by default
        ];

        // Define allowed HTTP methods for specific actions
        $behaviors['verbFilter'] = [
            'class' => VerbFilter::class,
            'actions' => [
                'index' => ['GET'],
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
     * Retrieves all Dashboards (Views) for the current user.
     * If no dashboards exist, a default one is created.
     *
     * @return array|Dashboard[] The list of Dashboard models.
     * @throws ForbiddenHttpException if not authenticated.
     */
    public function actionIndex()
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
                // Optional: Throw an exception if default creation fails
            }
        }
        return $dashboards;
    }

    /**
     * Displays a single Dashboard model by ID.
     *
     * @param int $id The ID of the dashboard.
     * @return Dashboard The loaded dashboard model.
     * @throws NotFoundHttpException if the dashboard does not exist.
     * @throws ForbiddenHttpException if not authenticated or not owned by user.
     */
    public function actionView($id)
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

    /**
     * Change of currently selected view (dashboard) to one selected by ID.
     * Sets the specified view as active and returns its components.
     *
     * Endpoint: POST /dashboards/change-active/{viewId}
     *
     * @param int $viewId ID of the dashboard to activate.
     * @return array The components of the newly active dashboard.
     * @throws NotFoundHttpException if the view does not exist or does not belong to the user.
     * @throws ForbiddenHttpException if not authenticated.
     */
    public function actionChangeActive($viewId)
    {
        $this->checkAccess();
        $userId = Yii::$app->user->getId();

        $view = $this->findModel($viewId); // Already checks ownership

        // Deactivate all current active dashboards for the user
        Dashboard::updateAll(['active' => 0], ['user_id' => $userId, 'active' => 1]);

        // Activate the requested dashboard
        $view->active = 1;
        $view->save(false); // Skip validation since we are only changing 'active'

        // Return the components of the newly active view
        return $this->getComponentsOfDashboard($viewId);
    }

    /**
     * Creates a new Component for a specific Dashboard.
     *
     * Endpoint: POST /dashboards/create-component
     * Body: { "view_id": 1, "config": "{...}", "order": 1 }
     *
     * NOTE: The original action accepted parameters via query string, REST prefers body data.
     * This implementation uses body data for config and order, and assumes view_id is also in the body or route.
     *
     * @return array|bool The created component model or validation errors.
     * @throws ForbiddenHttpException if not authenticated.
     */
    public function actionCreateComponent()
    {
        $this->checkAccess();

        $request = Yii::$app->request;
        $viewId = $request->post('view_id');
        $config = $request->post('config');
        $order = $request->post('order');

        // Verify the dashboard exists and belongs to the user
        $this->findModel($viewId);

        $component = new Component();
        $component->view_id = $viewId;
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

        // Basic check: ensure component's parent view belongs to the user
        $this->findModel($component->view_id);

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

        // Basic check: ensure component's parent view belongs to the user
        $this->findModel($component->view_id);

        if ($component->delete()) {
            Yii::$app->response->statusCode = 204; // No Content
            return true;
        }

        Yii::$app->response->statusCode = 500;
        return false;
    }

    /**
     * Returns a map of dashboard IDs to their refresh times.
     *
     * Endpoint: GET /dashboards/refresh-times
     *
     * @return array Map of [dashboardId => refreshTime]
     * @throws ForbiddenHttpException if not authenticated.
     */
    public function actionGetRefreshTimes()
    {
        $this->checkAccess();
        $userId = Yii::$app->user->getId();
        $dashboards = Dashboard::findAll(['user_id' => $userId]);
        $refresh_times = [];

        foreach ($dashboards as $dashboard) {
            $refresh_times[$dashboard->id] = $dashboard->refresh_time ?? '0';
        }

        return $refresh_times;
    }

    /**
     * Updates order of components in a dashboard.
     *
     * Endpoint: POST /dashboards/update-component-order/{viewId}
     * Body: [{ "id": 1, "order": 1 }, { "id": 2, "order": 2 }, ...]
     *
     * @param int $viewId ID of the dashboard to update.
     * @return bool True on successful update.
     * @throws NotFoundHttpException if the dashboard is not found.
     * @throws ForbiddenHttpException if not authenticated or not owned by user.
     */
    public function actionUpdateComponentOrder($viewId)
    {
        $this->checkAccess();
        $loggedUserId = Yii::$app->user->getId();

        // Check view existence and ownership
        $this->findModel($viewId);

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
                $component = Component::findOne(['id' => $value['id'], 'view_id' => $viewId]);

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
     * @param int $viewId The ID of the dashboard.
     * @return array|Component[] The list of components.
     * @throws NotFoundHttpException if the dashboard does not exist or does not belong to the user.
     */
    protected function getComponentsOfDashboard($viewId)
    {
        // findModel will throw an exception if the view doesn't exist or doesn't belong to the user
        $this->findModel($viewId);

        return Component::findAll(['view_id' => $viewId]);
    }
}