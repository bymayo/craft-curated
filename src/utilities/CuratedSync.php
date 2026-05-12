<?php

namespace bymayo\curated\utilities;

use Craft;
use craft\base\Utility;

class CuratedSync extends Utility
{
    public static function displayName(): string
    {
        return Craft::t('curated', 'Curated Sync');
    }

    public static function id(): string
    {
        return 'curated-sync';
    }

    public static function icon(): ?string
    {
        return 'list-ol';
    }

    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('curated/_utility');
    }
}
