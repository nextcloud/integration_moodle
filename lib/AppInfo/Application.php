<?php
/**
 * Nextcloud - Moodle
 *
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Moodle\AppInfo;


use OCP\IConfig;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;

use OCA\Moodle\Dashboard\MoodleWidget;
use OCA\Moodle\Search\MoodleSearchCoursesProvider;
use OCA\Moodle\Search\MoodleSearchModulesProvider;
use OCA\Moodle\Search\MoodleSearchUpcomingProvider;

/**
 * Class Application
 *
 * @package OCA\Moodle\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'integration_moodle';
	/**
	 * @var mixed
	 */
	private $config;

	/**
	 * Constructor
	 *
	 * @param array $urlParams
	 */
	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);

		$container = $this->getContainer();
		$this->config = $container->get(IConfig::class);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerDashboardWidget(MoodleWidget::class);

		if ($this->config->getAppValue(self::APP_ID, 'search_disabled', '0') === '0') {
			$context->registerSearchProvider(MoodleSearchCoursesProvider::class);
			$context->registerSearchProvider(MoodleSearchModulesProvider::class);
			$context->registerSearchProvider(MoodleSearchUpcomingProvider::class);
		}
	}

	public function boot(IBootContext $context): void {
	}
}

