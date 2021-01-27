<?php
/**
 * Nextcloud - Moodle
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Julien Veyssier <eneiluj@posteo.net>
 * @copyright Julien Veyssier 2020
 */

return [
    'routes' => [
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setAdminConfig', 'url' => '/admin-config', 'verb' => 'PUT'],
        ['name' => 'moodleAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
        ['name' => 'moodleAPI#getMoodleUrl', 'url' => '/url', 'verb' => 'GET'],
        ['name' => 'moodleAPI#getMoodleAvatar', 'url' => '/avatar', 'verb' => 'GET'],
        ['name' => 'moodleAPI#getToken', 'url' => '/get-token', 'verb' => 'POST'],
    ]
];
