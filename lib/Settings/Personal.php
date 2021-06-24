<?php
namespace OCA\Moodle\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Services\IInitialState;
use OCP\IConfig;
use OCP\Settings\ISettings;

use OCA\Moodle\AppInfo\Application;

class Personal implements ISettings {

	/**
	 * @var IConfig
	 */
	private $config;
	/**
	 * @var IInitialState
	 */
	private $initialStateService;
	/**
	 * @var string|null
	 */
	private $userId;

	public function __construct(IConfig $config,
								IInitialState $initialStateService,
								?string $userId) {
		$this->config = $config;
		$this->initialStateService = $initialStateService;
		$this->userId = $userId;
	}

    /**
     * @return TemplateResponse
     */
    public function getForm(): TemplateResponse {
        $token = $this->config->getUserValue($this->userId, Application::APP_ID, 'token');
        $url = $this->config->getUserValue($this->userId, Application::APP_ID, 'url');
        $searchCoursesEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_courses_enabled', '0');
        $searchModulesEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_modules_enabled', '0');
        $searchUpcomingEnabled = $this->config->getUserValue($this->userId, Application::APP_ID, 'search_upcoming_enabled', '0');
        $userName = $this->config->getUserValue($this->userId, Application::APP_ID, 'user_name');
        $checkSsl = $this->config->getUserValue($this->userId, Application::APP_ID, 'check_ssl', '1') === '1';

        $searchDisabled = $this->config->getAppValue(Application::APP_ID, 'search_disabled', '0') === '1';

        $userConfig = [
            'token' => $token,
            'url' => $url,
            'search_courses_enabled' => ($searchCoursesEnabled === '1'),
            'search_modules_enabled' => ($searchModulesEnabled === '1'),
            'search_upcoming_enabled' => ($searchUpcomingEnabled === '1'),
            'user_name' => $userName,
            'check_ssl' => $checkSsl,
            'search_disabled' => $searchDisabled,
        ];
        $this->initialStateService->provideInitialState('user-config', $userConfig);
        return new TemplateResponse(Application::APP_ID, 'personalSettings');
    }

    public function getSection(): string {
        return 'connected-accounts';
    }

    public function getPriority(): int {
        return 15;
    }
}
