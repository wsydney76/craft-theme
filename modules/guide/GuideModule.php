<?php

namespace modules\guide;

use Craft;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterTemplateRootsEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\App;
use craft\records\Widget;
use craft\services\Dashboard;
use craft\services\UserPermissions;
use craft\web\twig\variables\Cp;
use craft\web\UrlManager;
use craft\web\View;
use modules\guide\assetbundles\GuideAssets;
use modules\guide\widgets\GuideWidget;
use yii\base\Event;
use yii\base\Module;
use yii\web\User;

class GuideModule extends Module
{
    public function init()
    {


        Craft::setAlias('@modules/guide', $this->getBasePath());

        // Base template directory
        Event::on(
            View::class,
            View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $e) {
            $e->roots['guide'] = $this->getBasePath() .  '/templates';
            $e->roots['_guide'] = App::parseEnv('@templates') . '/_guide';
        });

        // Set routes
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES, function(RegisterUrlRulesEvent $event) {
            $event->rules['guide/<guideSlug:.*>'] = ['template' => 'guide/guide'];
            $event->rules['guide-ajax/<guideSlug:.*>'] = ['template' => 'guide/guideajax'];
        });

        // Set Nav
        Event::on(
            Cp::class,
            Cp::EVENT_REGISTER_CP_NAV_ITEMS, function($event) {
            $event->navItems[] = ['url' => 'guide/guide', 'label' => 'Guide', 'icon' => '@app/icons/routes.svg'];
        });

        // Register Widget
        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES, function(RegisterComponentTypesEvent $event) {
            $event->types[] = GuideWidget::class;
        }
        );

        // Control user widgets
        Event::on(
            User::class,
            User::EVENT_AFTER_LOGIN, function() {
                $this->_afterUserLogin();
        });

        // Register AssetBundle for guide
        if (Craft::$app->request->isCpRequest) {
            Event::on(
                View::class,
                View::EVENT_BEFORE_RENDER_TEMPLATE, function() {
                Craft::$app->view->registerAssetBundle(GuideAssets::class);;
            }
            );
        }

        // Register Edit Screen extensions
        Craft::$app->view->hook('cp.entries.edit.details', function(&$context) {
            if ($context['entry'] != null) {
                return Craft::$app->view->renderTemplate('guide/editorbutton.twig', ['entry' => $context['entry']]);
            }
            return '';
        });


        parent::init();
    }

    private function _afterUserLogin()
    {
        $user = Craft::$app->user->identity;
        $widgetType = GuideWidget::class;

        if (!$user->admin) {
            $query = Widget::find()
                ->where(['userId' => $user->id])
                ->andWhere(['type' => $widgetType]);

            if (!$query->count()) {
                $widget = new Widget([
                    'userId' => $user->id,
                    'type' => $widgetType,
                    'enabled' => 1,
                    'sortOrder' => 99
                ]);
                $widget->save();
            }
        }
    }
}
