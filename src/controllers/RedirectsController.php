<?php

namespace rocketpark\redirectify\controllers;

use Craft;
use craft\web\Controller;
use rocketpark\redirectify\Plugin;
use rocketpark\redirectify\models\Redirect;
use yii\web\Response;
use yii\web\NotFoundHttpException;

/**
 * Redirects Controller
 */
class RedirectsController extends Controller
{
    /**
     * List all redirects
     */
    public function actionIndex(): Response
    {
        $redirects = Plugin::$plugin->redirects->getAllRedirects();

        return $this->renderTemplate('redirectify/redirects/index', [
            'redirects' => $redirects,
        ]);
    }

    /**
     * Edit a redirect (or create new)
     */
    public function actionEdit(?int $redirectId = null, ?Redirect $redirect = null): Response
    {
        if ($redirect === null) {
            if ($redirectId !== null) {
                $redirect = Plugin::$plugin->redirects->getRedirectById($redirectId);
                if (!$redirect) {
                    throw new NotFoundHttpException('Redirect not found');
                }
            } else {
                $redirect = new Redirect();
            }
        }

        $sites = Craft::$app->getSites()->getAllSites();

        return $this->renderTemplate('redirectify/redirects/edit', [
            'redirect' => $redirect,
            'sites' => $sites,
            'matchTypeOptions' => Redirect::getMatchTypeOptions(),
            'statusCodeOptions' => Redirect::getStatusCodeOptions(),
            'isNew' => !$redirect->id,
        ]);
    }

    /**
     * Save a redirect
     */
    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $request = Craft::$app->getRequest();
        $redirectId = $request->getBodyParam('redirectId');

        if ($redirectId) {
            $redirect = Plugin::$plugin->redirects->getRedirectById($redirectId);
            if (!$redirect) {
                throw new NotFoundHttpException('Redirect not found');
            }
        } else {
            $redirect = new Redirect();
        }

        // Populate the model
        $redirect->siteId = $request->getBodyParam('siteId') ?: null;
        $redirect->sourceUrl = $request->getBodyParam('sourceUrl');
        $redirect->destinationUrl = $request->getBodyParam('destinationUrl');
        $redirect->matchType = $request->getBodyParam('matchType');
        $redirect->statusCode = (int) $request->getBodyParam('statusCode');
        $redirect->enabled = (bool) $request->getBodyParam('enabled');

        // Validate
        if (!$redirect->validate()) {
            Craft::$app->getSession()->setError('Could not save redirect.');
            
            return $this->renderTemplate('redirectify/redirects/edit', [
                'redirect' => $redirect,
                'sites' => Craft::$app->getSites()->getAllSites(),
                'matchTypeOptions' => Redirect::getMatchTypeOptions(),
                'statusCodeOptions' => Redirect::getStatusCodeOptions(),
                'isNew' => !$redirect->id,
            ]);
        }

        // Save
        if (!Plugin::$plugin->redirects->saveRedirect($redirect)) {
            Craft::$app->getSession()->setError('Could not save redirect.');
            
            return $this->renderTemplate('redirectify/redirects/edit', [
                'redirect' => $redirect,
                'sites' => Craft::$app->getSites()->getAllSites(),
                'matchTypeOptions' => Redirect::getMatchTypeOptions(),
                'statusCodeOptions' => Redirect::getStatusCodeOptions(),
                'isNew' => !$redirect->id,
            ]);
        }

        Craft::$app->getSession()->setNotice('Redirect saved.');

        return $this->redirectToPostedUrl($redirect);
    }

    /**
     * Delete a redirect
     */
    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $redirectId = Craft::$app->getRequest()->getRequiredBodyParam('id');

        if (Plugin::$plugin->redirects->deleteRedirect($redirectId)) {
            return $this->asJson(['success' => true]);
        }

        return $this->asJson(['success' => false]);
    }

    /**
     * Export redirects to CSV
     */
    public function actionExport(): Response
    {
        $csv = Plugin::$plugin->redirects->exportToCsv();
        
        return $this->response->sendContentAsFile(
            $csv,
            'redirects-' . date('Y-m-d') . '.csv',
            [
                'mimeType' => 'text/csv',
            ]
        );
    }

    /**
     * Import redirects from CSV
     */
    public function actionImport(): Response
    {
        $this->requirePostRequest();
        
        $file = $_FILES['file'] ?? null;
        
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Craft::$app->getSession()->setError('No file uploaded or upload error.');
            return $this->redirect('redirectify/redirects');
        }
        
        $csvContent = file_get_contents($file['tmp_name']);
        $result = Plugin::$plugin->redirects->importFromCsv($csvContent);
        
        if ($result['success'] > 0) {
            Craft::$app->getSession()->setNotice(
                "Successfully imported {$result['success']} redirect(s)." .
                ($result['failed'] > 0 ? " {$result['failed']} failed." : "")
            );
        } else {
            Craft::$app->getSession()->setError('No redirects imported. ' . implode(' ', $result['errors']));
        }
        
        return $this->redirect('redirectify/redirects');
    }
}
