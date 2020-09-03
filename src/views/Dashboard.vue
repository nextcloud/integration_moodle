<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template v-slot:empty-content>
			<div v-if="state === 'no-token'">
				<a :href="settingsUrl">
					{{ t('integration_moodle', 'Click here to configure the access to your Moodle account.') }}
				</a>
			</div>
			<div v-else-if="state === 'error'">
				<a :href="settingsUrl">
					{{ t('integration_moodle', 'Incorrect access token.') }}
					{{ t('integration_moodle', 'Click here to configure the access to your Moodle account.') }}
				</a>
			</div>
			<div v-else-if="state === 'ok'">
				{{ t('integration_moodle', 'Nothing to show') }}
			</div>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { getLocale } from '@nextcloud/l10n'

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			moodleUrl: null,
			notifications: [],
			locale: getLocale(),
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/linked-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
		}
	},

	computed: {
		showMoreUrl() {
			return this.moodleUrl + '/web/notifications'
		},
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getAuthorAvatarUrl(n),
					// avatarUsername: '',
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getMainText(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastTimestamp() {
			let maxTs = 0
			this.notifications.forEach((n) => {
				if (n.time > maxTs) {
					maxTs = n.time
				}
			})
			return (maxTs === 0) ? null : maxTs
		},
		lastMoment() {
			return moment(this.lastDate)
		},
	},

	beforeMount() {
		this.launchLoop()
	},

	mounted() {
	},

	methods: {
		async launchLoop() {
			// get moodle URL first
			try {
				const response = await axios.get(generateUrl('/apps/integration_moodle/url'))
				this.moodleUrl = response.data.replace(/\/+$/, '')
			} catch (error) {
				console.debug(error)
			}
			// then launch the loop
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 30000)
		},
		fetchNotifications() {
			const req = {}
			req.params = {
				since: this.lastTimestamp,
			}
			axios.get(generateUrl('/apps/integration_moodle/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_moodle', 'Failed to get Moodle notifications.'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			const toAdd = this.filter(newNotifications)
			this.notifications = toAdd.concat(this.notifications)
		},
		filter(notifications) {
			// no filtering for the moment
			return notifications
		},
		getNotificationTarget(n) {
			if (['event', 'recent'].includes(n.type)) {
				return n.viewurl
			}
			return ''
		},
		getMainText(n) {
			if (['recent'].includes(n.type)) {
				return n.name
			} else if (['event'].includes(n.type)) {
				return n.name
			}
			return ''
		},
		getSubline(n) {
			if (['recent'].includes(n.type)) {
				return n.coursename
			} else if (['event'].includes(n.type)) {
				return n.course.fullname
			}
			return ''
		},
		html2text(s) {
			if (!s || s === '') {
				return ''
			}
			const temp = document.createElement('template')
			s = s.trim()
			temp.innerHTML = s
			return temp.content.firstChild.textContent
		},
		getUniqueKey(n) {
			return n.type + n.id
		},
		getAuthorAvatarUrl(n) {
			if (['event'].includes(n.type)) {
				console.debug(n.course.courseimage)
				return n.course.courseimage
			} else if (['recent'].includes(n.type)) {
				const el = document.createElement('img')
				el.innerHTML = n.icon
				const realUrl = el.firstChild.getAttribute('src')
				return generateUrl('/apps/integration_moodle/avatar?') + encodeURIComponent('url') + '=' + encodeURIComponent(realUrl)
			}
			return ''
		},
		getNotificationTypeImage(n) {
			if (n.type === 'event') {
				return generateUrl('/svg/integration_moodle/calendar?color=ffffff')
			} else if (['recent'].includes(n.type)) {
				return generateUrl('/svg/integration_moodle/time?color=ffffff')
			}
			return ''
		},
		getFormattedDate(n) {
			return moment(n.created_at).locale(this.locale).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
</style>
