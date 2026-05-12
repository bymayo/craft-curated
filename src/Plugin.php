<?php

namespace bymayo\curate;

use bymayo\curate\behaviors\ElementQueryBehavior;
use bymayo\curate\fields\CuratedRelations;
use bymayo\curate\models\Settings;
use bymayo\curate\services\Curate;
use Craft;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\db\ElementQuery;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use yii\base\Event;

/**
 * Curate plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Jason Mayo <jason@bymayo.co.uk>
 * @copyright Jason Mayo
 * @license MIT
 * @property-read Curate $curate
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => ['curate' => Curate::class],
        ];
    }

    public function init(): void
    {
        parent::init();
        $this->attachEventHandlers();
    }

    public function getControllerNamespace(): string
    {
        return 'bymayo\curate\controllers';
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('curate/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'config' => Craft::$app->getConfig()->getConfigFromFile('curate'),
        ]);
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = CuratedRelations::class;
            }
        );

        Event::on(
            ElementQuery::class,
            ElementQuery::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $event) {
                $event->behaviors['curate'] = ElementQueryBehavior::class;
            }
        );
    }
}
