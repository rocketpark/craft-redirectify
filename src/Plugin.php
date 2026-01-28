<?php

namespace rocketpark\redirectify;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\base\Model;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use craft\web\ErrorHandler;
use craft\events\ExceptionEvent;
use craft\events\ModelEvent;
use craft\elements\Entry;
use craft\console\Application as ConsoleApplication;
use yii\base\Event;
use yii\web\HttpException;
use rocketpark\redirectify\models\Settings;
use rocketpark\redirectify\services\RedirectService;
use rocketpark\redirectify\console\controllers\ImportController;

/**
 * Redirectify plugin for Craft CMS 5
 *
 * Manage 301/302 redirects and track 404 errors
 *
 * @property RedirectService $redirects
 * @property Settings $settings
 */
class Plugin extends BasePlugin
{
    /**
     * @var Plugin|null
     */
    public static ?Plugin $plugin = null;

    /**
     * @var string
     */
    public string $schemaVersion = '1.0.0';

    /**
     * @var bool
     */
    public bool $hasCpSettings = true;

    /**
     * @var bool
     */
    public bool $hasCpSection = true;

    /**
     * @inheritdoc
     */
    public function init(): void
    {
        parent::init();
        self::$plugin = $this;

        // Register services
        $this->setComponents([
            'redirects' => RedirectService::class,
        ]);

        // Register console controllers
        if (Craft::$app instanceof ConsoleApplication) {
            $this->controllerNamespace = 'rocketpark\\redirectify\\console\\controllers';
        }

        // Defer most setup tasks until Craft is fully initialized
        Craft::$app->onInit(function() {
            $this->registerEventListeners();
            $this->registerCpUrlRules();
            $this->registerEntryEventListeners();
        });

        Craft::info(
            'Redirectify plugin loaded',
            __METHOD__
        );
    }

    /**
     * Register event listeners
     */
    protected function registerEventListeners(): void
    {
        // Listen for 404 errors
        Event::on(
            ErrorHandler::class,
            ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION,
            function(ExceptionEvent $event) {
                $exception = $event->exception;
                
                // Only handle 404 errors
                if ($exception instanceof HttpException && $exception->statusCode === 404) {
                    $this->redirects->handleNotFoundException();
                }
            }
        );
    }

    /**
     * Register CP URL rules
     */
    protected function registerCpUrlRules(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function(RegisterUrlRulesEvent $event) {
                $event->rules['redirectify'] = 'redirectify/redirects/index';
                $event->rules['redirectify/redirects'] = 'redirectify/redirects/index';
                $event->rules['redirectify/redirects/new'] = 'redirectify/redirects/edit';
                $event->rules['redirectify/redirects/<redirectId:\d+>'] = 'redirectify/redirects/edit';
                $event->rules['redirectify/redirects/export'] = 'redirectify/redirects/export';
                $event->rules['redirectify/redirects/import'] = 'redirectify/redirects/import';
                $event->rules['redirectify/stats'] = 'redirectify/stats/index';
                $event->rules['redirectify/settings'] = 'redirectify/settings/index';
            }
        );
    }

    /**
     * Register entry event listeners for automatic slug change redirects
     */
    protected function registerEntryEventListeners(): void
    {
        Event::on(
            Entry::class,
            Entry::EVENT_AFTER_SAVE,
            function(ModelEvent $event) {
                $settings = $this->getSettings();
                
                // Only create redirects if setting is enabled
                if (!$settings->createEntryRedirects) {
                    return;
                }
                
                /** @var Entry $entry */
                $entry = $event->sender;
                
                // Only handle existing entries (not new ones)
                if ($entry->duplicateOf !== null || !$entry->id || $event->isNew) {
                    return;
                }
                
                // Check if the URI has changed
                if ($entry->isFieldDirty('uri')) {
                    $this->redirects->createRedirectFromEntryUriChange($entry);
                }
            }
        );
    }

    /**
     * @inheritdoc
     */
    public function getCpNavItem(): ?array
    {
        $navItem = parent::getCpNavItem();
        
        $navItem['label'] = 'Redirectify';
        $navItem['subnav'] = [
            'redirects' => [
                'label' => 'Redirects',
                'url' => 'redirectify/redirects',
            ],
            'stats' => [
                'label' => '404 Statistics',
                'url' => 'redirectify/stats',
            ],
            'settings' => [
                'label' => 'Settings',
                'url' => 'redirectify/settings',
            ],
        ];

        return $navItem;
    }

    /**
     * @inheritdoc
     */
    protected function createSettingsModel(): ?Model
    {
        return new Settings();
    }

    /**
     * @inheritdoc
     */
    protected function settingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate(
            'redirectify/settings',
            [
                'settings' => $this->getSettings(),
            ]
        );
    }
}
