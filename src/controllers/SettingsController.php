<?php

namespace rocketpark\redirectify\controllers;

use Craft;
use craft\web\Controller;
use rocketpark\redirectify\Plugin;
use yii\web\Response;

/**
 * Settings Controller
 */
class SettingsController extends Controller
{
    /**
     * Show settings page
     */
    public function actionIndex(): Response
    {
        return $this->renderTemplate('redirectify/settings', [
            'settings' => Plugin::$plugin->getSettings(),
        ]);
    }

    /**
     * Save settings
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $settings = Plugin::$plugin->getSettings();

        $settings->trackStatistics = (bool) $request->getBodyParam('trackStatistics');
        $settings->stripQueryStrings = (bool) $request->getBodyParam('stripQueryStrings');
        $settings->preserveQueryStrings = (bool) $request->getBodyParam('preserveQueryStrings');
        $settings->maxStatistics = (int) $request->getBodyParam('maxStatistics');
        $settings->createEntryRedirects = (bool) $request->getBodyParam('createEntryRedirects');

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError('Could not save settings.');
            return $this->renderTemplate('redirectify/settings', [
                'settings' => $settings,
            ]);
        }

        if (!Craft::$app->getPlugins()->savePluginSettings(Plugin::$plugin, $settings->toArray())) {
            Craft::$app->getSession()->setError('Could not save settings.');
            return $this->renderTemplate('redirectify/settings', [
                'settings' => $settings,
            ]);
        }

        Craft::$app->getSession()->setNotice('Settings saved.');

        return $this->redirectToPostedUrl();
    }
}
