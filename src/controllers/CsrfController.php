<?php

namespace mutation\filecache\controllers;

use Craft;
use craft\web\Controller;
use yii\web\Response;

class CsrfController extends Controller
{
    protected $allowAnonymous = true;

    public function actionInput(): Response
    {
        $input = '<input type="hidden" 
            name="' . Craft::$app->getConfig()->getGeneral()->csrfTokenName . '" 
            value="' . Craft::$app->getRequest()->getCsrfToken() . '">';
        return $this->asRaw($input);
    }

    public function actionJs(): Response
    {
        return $this->asJson(array(
            'csrfTokenName' => Craft::$app->getConfig()->getGeneral()->csrfTokenName,
            'csrfTokenValue' => Craft::$app->getRequest()->getCsrfToken(),
        ));
    }
}