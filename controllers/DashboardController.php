<?php

namespace app\controllers;

use Yii;
use app\models\User;
use yii\web\Controller;


class DashboardController extends Controller
{

    public $layout = 'dashboardLayout';

    public function actionIndex()
    {
        if (Yii::$app->user->isGuest) { // user not logged in
            return $this->goHome();
        }
        
        $authToken = null;
        $user = Yii::$app->user->identity;
        if ($user instanceof User) {
            $authToken = $user->getAuthKey();
        }
        return $this->render('index', [
            'authToken' => $authToken
        ]);
    }
}
