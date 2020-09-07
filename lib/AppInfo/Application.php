<?php
/**
 * Nextcloud - Moodle
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Moodle\AppInfo;

use OCP\IContainer;

use OCP\AppFramework\App;
use OCP\AppFramework\IAppContainer;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Moodle\Controller\PageController;
use OCA\Moodle\Dashboard\MoodleWidget;
use OCA\Moodle\Search\MoodleSearchProvider;

/**
 * Class Application
 *
 * @package OCA\Moodle\AppInfo
 */
class Application extends App implements IBootstrap {

    public const APP_ID = 'integration_moodle';

    /**
     * Constructor
     *
     * @param array $urlParams
     */
    public function __construct(array $urlParams = []) {
        parent::__construct(self::APP_ID, $urlParams);

        $container = $this->getContainer();
    }

    public function register(IRegistrationContext $context): void {
        $context->registerDashboardWidget(MoodleWidget::class);
        $context->registerSearchProvider(MoodleSearchProvider::class);
    }

    public function boot(IBootContext $context): void {
    }
}

