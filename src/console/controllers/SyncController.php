<?php

namespace bymayo\curated\console\controllers;

use bymayo\curated\Plugin;
use craft\console\Controller;
use yii\console\ExitCode;

/**
 * Sync curated lists from the native relations they track.
 */
class SyncController extends Controller
{
    /**
     * Append any native relations that aren't already in the curated list.
     *
     * Walks every Curated field that has a tracked relation field, finds every
     * parent element using that field, and tops up the curated order with any
     * missing related elements. Idempotent — running it twice does nothing
     * the second time.
     */
    public function actionIndex(): int
    {
        $summary = Plugin::getInstance()->curated->syncAll();

        $this->stdout(sprintf(
            "Synced %d curated field(s): appended %d new relation(s) across %d parent element(s).\n",
            $summary['fields'],
            $summary['appended'],
            $summary['parents']
        ));

        return ExitCode::OK;
    }
}
