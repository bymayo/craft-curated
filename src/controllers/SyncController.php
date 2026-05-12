<?php

namespace bymayo\curated\controllers;

use bymayo\curated\Plugin;
use Craft;
use craft\web\Controller;
use yii\web\Response;

class SyncController extends Controller
{
    public function actionRun(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission('utility:curated-sync');

        $summary = Plugin::getInstance()->curated->syncAll();

        Craft::$app->getSession()->setNotice(Craft::t('curated', sprintf(
            'Synced %d field(s): %d new relation(s) appended across %d parent(s).',
            $summary['fields'],
            $summary['appended'],
            $summary['parents']
        )));

        return $this->redirectToPostedUrl();
    }
}
