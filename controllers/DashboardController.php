<?php

namespace app\controllers;

use Yii;

use yii\web\Controller;


class DashboardController extends Controller
{

    public $layout = 'dashboardLayout';

    public function actionIndex()
    {
        // Get current user's auth token
        $authToken = null;
        if (!Yii::$app->user->isGuest) {
            $user = Yii::$app->user->identity;
            if ($user instanceof User) {
                $authToken = $user->getAuthKey();
            }
        }
        
        return $this->render('index', [
            'authToken' => $authToken
        ]);
    }
}
