<?php

namespace bymayo\curated\controllers;

use bymayo\curated\fields\Curated as CuratedField;
use bymayo\curated\Plugin;
use Craft;
use craft\web\Controller;
use yii\web\BadRequestHttpException;
use yii\web\Response;

class SortController extends Controller
{
    public function actionRun(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();
        $this->requireLogin();

        $request = Craft::$app->getRequest();
        $fieldId = (int)$request->getRequiredBodyParam('fieldId');
        $sourceId = (int)$request->getBodyParam('sourceId', 0) ?: null;
        $siteId = (int)$request->getBodyParam('siteId', 0) ?: null;
        $sortKey = (string)$request->getRequiredBodyParam('sortKey');
        $ids = (array)$request->getBodyParam('ids', []);

        $field = Craft::$app->getFields()->getFieldById($fieldId);
        if (!$field instanceof CuratedField) {
            throw new BadRequestHttpException('Invalid Curated field.');
        }

        $parent = null;
        if ($sourceId) {
            $parent = Craft::$app->getElements()->getElementById($sourceId, null, $siteId);
        }

        $sortedIds = Plugin::getInstance()->curated->sortIds($field, $parent, $sortKey, $ids);

        return $this->asJson(['ids' => $sortedIds]);
    }
}
