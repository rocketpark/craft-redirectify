<?php

namespace rocketpark\redirectify\controllers;

use Craft;
use craft\web\Controller;
use rocketpark\redirectify\Plugin;
use yii\web\Response;

/**
 * Stats Controller
 */
class StatsController extends Controller
{
    /**
     * List all 404 statistics
     */
    public function actionIndex(): Response
    {
        $stats = Plugin::$plugin->redirects->getAllStats();

        return $this->renderTemplate('redirectify/stats/index', [
            'stats' => $stats,
        ]);
    }

    /**
     * Delete a stats entry
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $statsId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::$plugin->redirects->deleteStats($statsId)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['success' => false]);
    }

    /**
     * Create a redirect from a 404 entry
     */
    public function actionCreateRedirect(): Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $statsId = $request->getRequiredBodyParam('statsId');
        $destinationUrl = $request->getRequiredBodyParam('destinationUrl');

        $redirect = Plugin::$plugin->redirects->createRedirectFromStats($statsId, $destinationUrl);

        if ($redirect) {
            Craft::$app->getSession()->setNotice('Redirect created successfully.');
            return $this->redirect('redirectify/redirects');
        }

        Craft::$app->getSession()->setError('Could not create redirect.');
        return $this->redirect('redirectify/stats');
    }
}
