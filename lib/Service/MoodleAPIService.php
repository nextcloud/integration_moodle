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

use Exception;
use OCP\IL10N;
use Psr\Log\LoggerInterface;
use OCP\Http\Client\IClientService;

class MoodleAPIService {
	/**
	 * @var string
	 */
	private $appName;
	/**
	 * @var LoggerInterface
	 */
	private $logger;
	/**
	 * @var IL10N
	 */
	private $l10n;
	/**
	 * @var \OCP\Http\Client\IClient
	 */
	private $client;

	/**
	 * Service to make requests to Moodle v1 API
	 */
	public function __construct (string $appName,
								LoggerInterface $logger,
								IL10N $l10n,
								IClientService $clientService) {
		$this->appName = $appName;
		$this->logger = $logger;
		$this->l10n = $l10n;
		$this->client = $clientService->newClient();
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param bool $checkSsl
	 * @param ?int $recentSince
	 * @return array
	 */
	public function getNotifications(string $url, string $accessToken, bool $checkSsl, ?int $recentSince): array {
		$params = [
			'wstoken' => $accessToken,
			'wsfunction' => 'block_recentlyaccesseditems_get_recent_items',
			'moodlewsrestformat' => 'json',
		];
		$recentItems = $this->request($url, 'webservice/rest/server.php', $checkSsl, $params);

		if (isset($recentItems['error'])) {
			return $recentItems;
		}

		// sort recent items by date DESC
		usort($recentItems, function($a, $b) {
			$ta = $a['timeaccess'] ?? 0;
			$tb = $b['timeaccess'] ?? 0;
			return ($ta > $tb) ? -1 : 1;
		});

		// get courses and set 'time'
		$courseIds = [];
		$recents = [];
		foreach ($recentItems as $k => $recentItem) {
			if (isset($recentItem['courseid']) && !in_array($recentItem['courseid'], $courseIds)) {
				$courseIds[] = $recentItem['courseid'];
			}
			$recent = $recentItem;
			$recent['time'] = $recentItem['timeaccess'] ?? 0;
			$recent['type'] = 'recent';
			$recents[] = $recent;
		}

		// get upcoming events
		$upcomingEvents = [];
		foreach ($courseIds as $courseId) {
			$params['wsfunction'] = 'core_calendar_get_calendar_upcoming_view';
			$params['courseid'] = $courseId;
			$oneRes = $this->request($url, 'webservice/rest/server.php', $checkSsl, $params);
			if (!isset($oneRes['error']) && isset($oneRes['events'])) {
				$upcomingEvents = array_merge($upcomingEvents, $oneRes['events']);
			}
		}
		// sort upcoming events by date ASC
		usort($upcomingEvents, function($a, $b) {
			$ta = $a['timestart'] ?? 0;
			$tb = $b['timestart'] ?? 0;
			return ($ta < $tb) ? -1 : 1;
		});

		foreach ($upcomingEvents as $k => $upcomingEvent) {
			$upcomingEvents[$k]['time'] = $upcomingEvent['timestart'] ?? 0;
			$upcomingEvents[$k]['type'] = 'event';
		}

		// filter by date
		if (!is_null($recentSince)) {
			$recents = array_filter($recents, function($elem) use ($recentSince) {
				$ts = intval($elem['time']);
				return $ts > $recentSince;
			});
		}

		return [
			'recents' => array_values($recents),
			'events' => array_values($upcomingEvents)
		];
	}

	/**
	 * @param string $url
	 * @param string $accessToken
	 * @param bool $checkSsl
	 * @param string $query
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function searchCourses(string $url, string $accessToken, bool $checkSsl, string $query, int $offset = 0, int $limit = 5): array {
		$params = [
			'wstoken' => $accessToken,
			'wsfunction' => 'core_course_search_courses',
			'moodlewsrestformat' => 'json',
			'criterianame' => 'search',
			'criteriavalue' => $query,
		];
		$searchResult = $this->request($url, 'webservice/rest/server.php', $checkSsl, $params);
		if ($searchResult['exception'] || $searchResult['error']) {
			return $searchResult;
		}
		return array_slice($searchResult['courses'], $offset, $limit);
	}

	public function searchModules(string $url, string $accessToken, bool $checkSsl, string $query, ?int $offset = 0, ?int $limit = 5): array {
		$query = strtolower($query);
		$params = [
			'wstoken' => $accessToken,
			'wsfunction' => 'core_course_search_courses',
			'moodlewsrestformat' => 'json',
			'criterianame' => 'search',
			'criteriavalue' => '',
		];
		$courses = $this->request($url, 'webservice/rest/server.php', $checkSsl, $params);
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
			$sections = $this->request($url, 'webservice/rest/server.php', $checkSsl, $params);
			if ($sections['exception'] || $sections['error']) {
				return $sections;
			}
			foreach ($sections as $ks => $section) {
				foreach ($section['modules'] as $km => $module) {
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
	 * @param bool $checkSsl
	 * @param string $query
	 * @param int $offset
	 * @param int $limit
	 * @return array
	 */
	public function searchUpcoming(string $url, string $accessToken, bool $checkSsl, string $query, int $offset = 0, int $limit = 5): array {
		$query = strtolower($query);
		$params = [
			'wstoken' => $accessToken,
			'wsfunction' => 'core_course_search_courses',
			'moodlewsrestformat' => 'json',
			'criterianame' => 'search',
			'criteriavalue' => '',
		];
		$courses = $this->request($url, 'webservice/rest/server.php', $checkSsl, $params);
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
			$results = $this->request($url, 'webservice/rest/server.php', $checkSsl, $params);
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
	 * @param bool $checkSsl
	 * @return string
	 * @throws Exception
	 */
	public function getMoodleAvatar(string $url, bool $checkSsl): string {
		if ($checkSsl) {
			$rawResult = $this->client->get($url)->getBody();
		} else {
			$rawResult = $this->client->get($url, ['verify' => false])->getBody();
		}
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
	 * @param bool $checkSsl
	 * @param array $params
	 * @param string $method
	 * @return array
	 */
	public function request(string $url, string $endPoint, bool $checkSsl = true, array $params = [], string $method = 'GET'): array {
		try {
			$url = $url . '/' . $endPoint;
			$options = [
				'headers' => [
					'User-Agent' => 'Nextcloud Moodle integration',
				]
			];

			if (!$checkSsl) {
				$options['verify'] = false;
			}

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
			} else {
				return ['error' => $this->l10n->t('Bad HTTP method')];
			}
			$body = $response->getBody();
			$respCode = $response->getStatusCode();

			if ($respCode >= 400) {
				return ['error' => $this->l10n->t('Bad credentials')];
			} else {
				return json_decode($body, true);
			}
		} catch (Exception $e) {
			$this->logger->warning('Moodle API error : '.$e, array('app' => $this->appName));
			return ['error' => $e->getMessage()];
		}
	}

	/**
	 * @param string $moodleUrl
	 * @param string $login
	 * @param string $password
	 * @param bool $checkSsl
	 * @return array
	 */
	public function getToken(string $moodleUrl, string $login, string $password, bool $checkSsl): array {
		$params = [
			'username' => $login,
			'password' => $password,
			'service' => 'moodle_mobile_app',
		];
		return $this->request($moodleUrl, 'login/token.php', $checkSsl, $params, 'POST');
	}
}
