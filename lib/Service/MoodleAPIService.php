<?php
/**
 * Nextcloud - moodle
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier
 * @copyright Julien Veyssier 2020
 */

namespace OCA\Moodle\Service;

use OCP\IL10N;
use OCP\ILogger;
use OCP\Http\Client\IClientService;

use OCA\Moodle\AppInfo\Application;

class MoodleAPIService {

	private $l10n;
	private $logger;

	/**
	 * Service to make requests to Moodle v1 API
	 */
	public function __construct (
		string $appName,
		ILogger $logger,
		IL10N $l10n,
		IClientService $clientService
	) {
		$this->appName = $appName;
		$this->l10n = $l10n;
		$this->logger = $logger;
		$this->clientService = $clientService;
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param ?int $recentSince
	 * @return array
	 */
	public function getNotifications(string $url, string $accessToken, ?int $recentSince): array {
		$params = [
			'wstoken' => $accessToken,
			'wsfunction' => 'block_recentlyaccesseditems_get_recent_items',
			'moodlewsrestformat' => 'json',
		];
		$recentItems = $this->request($url, 'webservice/rest/server.php', $params);

		if (isset($recentItems['error'])) {
			return $recentItems;
		}

		// sort recent items by date DESC
		$a = usort($recentItems, function($a, $b) {
			$ta = $a['timeaccess'];
			$tb = $b['timeaccess'];
			return ($ta > $tb) ? -1 : 1;
		});

		// get courses and set 'time'
		$courseIds = [];
		foreach ($recentItems as $k => $recentItem) {
			if (isset($recentItem['courseid']) && !in_array($recentItem['courseid'], $courseIds)) {
				$courseIds[] = $recentItem['courseid'];
			}
			$recentItems[$k]['time'] = $recentItem['timeaccess'];
			$recentItems[$k]['type'] = 'recent';
		}

		// get upcoming events
		$upcomingEvents = [];
		foreach ($courseIds as $courseId) {
			$params['wsfunction'] = 'core_calendar_get_calendar_upcoming_view';
			$params['courseid'] = $courseId;
			$oneRes = $this->request($url, 'webservice/rest/server.php', $params);
			if (!isset($oneRes['error']) && isset($oneRes['events'])) {
				$upcomingEvents = array_merge($upcomingEvents, $oneRes['events']);
			}
		}
		// sort upcoming events by date ASC
		$a = usort($upcomingEvents, function($a, $b) {
			$ta = $a['timestart'];
			$tb = $b['timestart'];
			return ($ta < $tb) ? -1 : 1;
		});

		foreach ($upcomingEvents as $k => $upcomingEvent) {
			$upcomingEvents[$k]['time'] = $upcomingEvent['timestart'];
			$upcomingEvents[$k]['type'] = 'event';
		}

		// filter by date
		if (!is_null($recentSince)) {
			$recentItems = array_filter($recentItems, function($elem) use ($recentSince) {
				$ts = intval($elem['time']);
				return $ts > $recentSince;
			});
		}

		return [
			'recents' => array_values($recentItems),
			'events' => array_values($upcomingEvents)
		];
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $query
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function searchCourses(string $url, string $accessToken, string $query, int $offset = 0, int $limit = 5): array {
		$params = [
			'wstoken' => $accessToken,
			'wsfunction' => 'core_course_search_courses',
			'moodlewsrestformat' => 'json',
			'criterianame' => 'search',
			'criteriavalue' => $query,
		];
		$searchResult = $this->request($url, 'webservice/rest/server.php', $params);
		if ($searchResult['exception'] || $searchResult['error']) {
			return $searchResult;
		}
		return array_slice($searchResult['courses'], $offset, $limit);
	}

	public function searchModules(string $url, string $accessToken, string $query, ?int $offset = 0, ?int $limit = 5): array {
		$query = strtolower($query);
		$params = [
			'wstoken' => $accessToken,
			'wsfunction' => 'core_course_search_courses',
			'moodlewsrestformat' => 'json',
			'criterianame' => 'search',
			'criteriavalue' => '',
		];
		$courses = $this->request($url, 'webservice/rest/server.php', $params);
		if ($courses['exception'] || $courses['error']) {
			return $courses;
		}
		$modules = [];
		foreach ($courses['courses'] as $course) {
			$params = [
				'wstoken' => $accessToken,
				'wsfunction' => 'core_course_get_contents',
				'moodlewsrestformat' => 'json',
				'courseid' => $course['id'],
			];
			$sections = $this->request($url, 'webservice/rest/server.php', $params);
			if ($sections['exception'] || $sections['error']) {
				return $sections;
			}
			foreach ($sections as $k => $section) {
				foreach ($section['modules'] as $k => $module) {
					$moduleName = strtolower($module['name']);
					if (strpos($moduleName, $query) !== false) {
						$module['section_name'] = $section['name'];
						$module['course_name'] = $course['displayname'];
						$modules[] = $module;
						if (count($modules) >= ($offset + $limit)) {
							return array_slice($modules, $offset, $limit);
						}
					}
				}
			}
		}

		return array_slice($modules, $offset, $limit);
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param string $query
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function searchUpcoming(string $url, string $accessToken, string $query, int $offset = 0, int $limit = 5): array {
		$query = strtolower($query);
		$params = [
			'wstoken' => $accessToken,
			'wsfunction' => 'core_course_search_courses',
			'moodlewsrestformat' => 'json',
			'criterianame' => 'search',
			'criteriavalue' => '',
		];
		$courses = $this->request($url, 'webservice/rest/server.php', $params);
		if ($courses['exception'] || $courses['error']) {
			return $courses;
		}
		$upcomings = [];
		foreach ($courses['courses'] as $course) {
			$params = [
				'wstoken' => $accessToken,
				'wsfunction' => 'core_calendar_get_calendar_upcoming_view',
				'moodlewsrestformat' => 'json',
				'courseid' => $course['id'],
			];
			$results = $this->request($url, 'webservice/rest/server.php', $params);
			if ($results['exception'] || $results['error']) {
				return $results;
			}
			foreach ($results['events'] as $k => $upcoming) {
				$upcomingName = strtolower($upcoming['name']);
				if (strpos($upcomingName, $query) !== false) {
					$upcoming['course_name'] = $course['displayname'];
					$upcomings[] = $upcoming;
					if (count($upcomings) >= ($offset + $limit)) {
						return array_slice($upcomings, $offset, $limit);
					}
				}
			}
		}

		return array_slice($upcomings, $offset, $limit);
	}

	/**
	 * @param string $url
	 * @return string
	 */
	public function getMoodleAvatar(string $url): string {
		$rawResult = $this->client->get($url)->getBody();
		$success = preg_match('/<svg.*/', $rawResult, $matches);
		//$result = $success === 1 ? $this->getBase64Svg($matches[0]) : $rawResult;
		if ($success === 1) {
			$result = '<?xml version="1.0"?>' . $matches[0];
			//$result = $this->getBase64Svg($result);
		} else {
			$result = $rawResult;
		}
		//error_log('RESult['.$success.'] '.$result);
		return $result;
	}

	private function getBase64Svg(string $svgString): string {
		return 'data:image/svg+xml;base64,' . base64_encode($svgString);
	}

	/**
	 * @param string $url
	 * @param string $endPoint
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function request(string $url, string $endPoint, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud Moodle integration',
				]
			];

			if (count($params) > 0) {
				if ($method === 'GET') {
					// manage array parameters
					$paramsContent = '';
					foreach ($params as $key => $value) {
						if (is_array($value)) {
							foreach ($value as $oneArrayValue) {
								$paramsContent .= $key . '[]=' . urlencode($oneArrayValue) . '&';
							}
							unset($params[$key]);
						}
					}
					$paramsContent .= http_build_query($params);
					$url .= '?' . $paramsContent;
				} else {
					$options['body'] = $params;
				}
			}

			if ($method === 'GET') {
				$response = $this->client->get($url, $options);
			} else if ($method === 'POST') {
				$response = $this->client->post($url, $options);
			} else if ($method === 'PUT') {
				$response = $this->client->put($url, $options);
			} else if ($method === 'DELETE') {
				$response = $this->client->delete($url, $options);
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (\Exception $e) {
			$this->logger->warning('Moodle API error : '.$e, array('app' => $this->appName));
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $moodleUrl
	 * @param string $login
	 * @param string $password
	 * @return array
	 */
	public function getToken(string $moodleUrl, string $login, string $password): array {
		$params = [
			'username' => $login,
			'password' => $password,
			'service' => 'moodle_mobile_app',
		];
		return $this->request($moodleUrl, 'login/token.php', $params, 'POST');
	}
}
