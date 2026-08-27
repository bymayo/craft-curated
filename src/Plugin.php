<?php

namespace bymayo\curated;

use bymayo\curated\behaviors\ElementQueryBehavior;
use bymayo\curated\fields\Curated as CuratedField;
use bymayo\curated\models\Settings;
use bymayo\curated\services\Curated;
use bymayo\curated\utilities\CuratedSync;
use Craft;
use craft\base\Element;
use craft\base\Model;
use craft\base\Plugin as BasePlugin;
use craft\elements\db\ElementQuery;
use craft\events\DefineBehaviorsEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use craft\services\Utilities;
use yii\base\Event;

/**
 * Curated plugin
 *
 * @method static Plugin getInstance()
 * @method Settings getSettings()
 * @author Jason Mayo <jason@bymayo.co.uk>
 * @copyright Jason Mayo
 * @license MIT
 * @property-read Curated $curated
 */
class Plugin extends BasePlugin
{
    public string $schemaVersion = '1.0.2';
    public bool $hasCpSettings = true;

    public static function config(): array
    {
        return [
            'components' => ['curated' => Curated::class],
        ];
    }

    public function init(): void
    {
        parent::init();

        if (Craft::$app instanceof \craft\console\Application) {
            $this->controllerNamespace = 'bymayo\\curated\\console\\controllers';
        }

        // Pre-rename field rows may still reference the old class FQN.
        if (!class_exists('bymayo\\curated\\fields\\CuratedRelations', false)) {
            class_alias(CuratedField::class, 'bymayo\\curated\\fields\\CuratedRelations');
        }

        $this->attachEventHandlers();
    }

    public function getControllerNamespace(): string
    {
        return Craft::$app instanceof \craft\console\Application
            ? 'bymayo\\curated\\console\\controllers'
            : 'bymayo\\curated\\controllers';
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('curated/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
            'config' => Craft::$app->getConfig()->getConfigFromFile('curated'),
        ]);
    }

    private function attachEventHandlers(): void
    {
        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = CuratedField::class;
            }
        );

        Event::on(
            ElementQuery::class,
            ElementQuery::EVENT_DEFINE_BEHAVIORS,
            function (DefineBehaviorsEvent $event) {
                $event->behaviors['curated'] = ElementQueryBehavior::class;
            }
        );

        Event::on(
            Utilities::class,
            Utilities::EVENT_REGISTER_UTILITIES,
            function (RegisterComponentTypesEvent $event) {
                $event->types[] = CuratedSync::class;
            }
        );

        Event::on(
            Element::class,
            Element::EVENT_AFTER_DELETE,
            function (\yii\base\Event $event) {
                /** @var Element $element */
                $element = $event->sender;
                if (!$element->id) {
                    return;
                }
                Plugin::getInstance()->curated->removeTargetEverywhere($element->id);
            }
        );

    }
}
